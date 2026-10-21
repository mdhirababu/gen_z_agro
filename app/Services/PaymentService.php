<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /** একটি খরচের (বা পরে বিক্রির) বিপরীতে টাকা নেওয়া-দেওয়া নথিভুক্ত করে */
    public function record(Model $payable, float $amount, string $paidAt, string $method, ?string $note, User $user): Payment
    {
        return DB::transaction(function () use ($payable, $amount, $paidAt, $method, $note, $user) {
            $locked = $payable::query()->withoutGlobalScope('farm')
                ->whereKey($payable->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'void') {
                throw ValidationException::withMessages(['amount' => 'বাতিল করা রেকর্ডে পেমেন্ট যোগ করা যায় না।']);
            }

            $amount = round($amount, 2);
            $due = round((float) $locked->total_amount - (float) $locked->paid_amount, 2);

            if ($amount <= 0 || $amount > $due + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => 'পরিমাণ ০-এর বেশি এবং বকেয়ার (৳ ' . number_format($due, 2) . ') বেশি হতে পারবে না।',
                ]);
            }

            $payment = new Payment(['amount' => $amount, 'paid_at' => $paidAt, 'method' => $method, 'note' => $note]);
            $payment->forceFill([
                'farm_id' => $locked->farm_id,
                'payable_type' => $locked->getMorphClass(),
                'payable_id' => $locked->getKey(),
                'status' => 'active',
                'created_by' => $user->id,
                'source' => 'manual',
            ])->save();

            $this->recalculate($locked);

            return $payment;
        });
    }

    /** পেমেন্ট বাতিল করে মূল রেকর্ডের পরিশোধিত অঙ্ক ও অবস্থা আবার হিসাব করে */
    public function void(Payment $payment, User $user, ?string $reason): bool
    {
        return DB::transaction(function () use ($payment, $user, $reason) {
            $locked = Payment::withoutGlobalScope('farm')->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'void') {
                return false;
            }

            $locked->forceFill([
                'status' => 'void',
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'voided_at' => now(),
            ])->save();

            $class = Relation::getMorphedModel($locked->payable_type);
            $payable = $class ? $class::query()->withoutGlobalScope('farm')->find($locked->payable_id) : null;

            if ($payable) {
                $this->recalculate($payable);
            }

            return true;
        });
    }

    /** সক্রিয় পেমেন্টের যোগফল থেকে paid_amount ও payment_status ঠিক করে */
    public function recalculate(Model $payable): void
    {
        $paid = round((float) Payment::withoutGlobalScope('farm')
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->where('status', 'active')
            ->sum('amount'), 2);

        $total = round((float) $payable->total_amount, 2);

        $status = $paid <= 0 ? 'unpaid' : ($paid + 0.001 >= $total ? 'paid' : 'partial');

        $payable->forceFill(['paid_amount' => $paid, 'payment_status' => $status])->save();
    }

    /**
     * ক্রেতার কাছ থেকে একবারে টাকা নেওয়া: পুরোনো বিক্রির বকেয়া আগে মেটে।
     * ফেরত দেয় কয়টি বিক্রিতে টাকা বসেছে।
     */
    public function receiveForCustomer(Customer $customer, float $amount, string $paidAt, string $method, ?string $note, User $user): int
    {
        return DB::transaction(function () use ($customer, $amount, $paidAt, $method, $note, $user) {
            $amount = round($amount, 2);

            $sales = Sale::withoutGlobalScope('farm')
                ->where('farm_id', $customer->farm_id)
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->where('payment_status', '!=', 'paid')
                ->orderBy('sold_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $totalDue = round((float) $sales->sum(fn($s) => (float) $s->total_amount - (float) $s->paid_amount), 2);

            if ($amount <= 0 || $amount > $totalDue + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => 'পরিমাণ ০-এর বেশি এবং মোট বকেয়ার (৳ ' . number_format($totalDue, 2) . ') বেশি হতে পারবে না।',
                ]);
            }

            $remaining = $amount;
            $count = 0;

            foreach ($sales as $sale) {
                if ($remaining <= 0.001) {
                    break;
                }

                $due = round((float) $sale->total_amount - (float) $sale->paid_amount, 2);
                $part = min($due, $remaining);

                $this->record($sale, $part, $paidAt, $method, $note, $user);

                $remaining = round($remaining - $part, 2);
                $count++;
            }

            return $count;
        });
    }
}
