<?php

namespace App\Services;

use App\Models\Cow;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerService
{
    public function __construct(private PaymentService $payments) {}

    /**
     * $pay = ['mode' => paid|unpaid|partial, 'amount' => ?, 'method' => ?, 'date' => ?]
     */
    public function createExpense(Farm $farm, array $data, float $total, array $pay, User $user): Expense
    {
        return DB::transaction(function () use ($farm, $data, $total, $pay, $user) {
            $expense = new Expense($data);
            $expense->forceFill([
                'farm_id' => $farm->id,
                'total_amount' => $total,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'status' => 'active',
                'created_by' => $user->id,
                'source' => 'manual',
            ])->save();

            $this->applyInitialPayment($expense, $total, $pay, $user);

            return $expense->refresh();
        });
    }

    /** বিক্রি নথিভুক্ত করে। গরু বিক্রি হলে একই লেনদেনে গরুটি sold হয়। */
    public function createSale(Farm $farm, array $data, float $total, array $pay, User $user): Sale
    {
        return DB::transaction(function () use ($farm, $data, $total, $pay, $user) {
            $cow = null;

            if ($data['sale_type'] === 'cow') {
                // গরুর সারি লক: একই গরু একসঙ্গে দুবার বিক্রি হতে পারে না
                $cow = Cow::withoutGlobalScope('farm')
                    ->where('farm_id', $farm->id)
                    ->whereKey($data['cow_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $cow || $cow->status !== Cow::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['cow_id' => 'এই গরুটি এখন বিক্রির জন্য সক্রিয় নেই।']);
                }

                if (empty($data['description'])) {
                    $data['description'] = 'গরু বিক্রি: ' . $cow->tag_number;
                }
            }

            $sale = new Sale($data);
            $sale->forceFill([
                'farm_id' => $farm->id,
                'total_amount' => $total,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'status' => 'active',
                'created_by' => $user->id,
                'source' => 'manual',
            ])->save();

            if ($cow) {
                $cow->forceFill(['status' => Cow::STATUS_SOLD, 'exit_date' => $data['sold_at']])->save();
            }

            $this->applyInitialPayment($sale, $total, $pay, $user);

            return $sale->refresh();
        });
    }

    /**
     * খরচ বা বিক্রি বাতিল (মোছা নয়)। এর সব সক্রিয় পেমেন্টও বাতিল হয়।
     * গরু বিক্রি বাতিল করলে গরুটি আবার সক্রিয় হয়, তবে প্ল্যানের সীমা শেষ থাকলে হয় না।
     */
    public function void(Model $record, User $user, string $reason): bool
    {
        return DB::transaction(function () use ($record, $user, $reason) {
            $locked = $record::query()->withoutGlobalScope('farm')
                ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'void') {
                return false;
            }

            if ($locked instanceof Sale && $locked->sale_type === 'cow' && $locked->cow_id) {
                $cow = Cow::withoutGlobalScope('farm')->withTrashed()
                    ->whereKey($locked->cow_id)->lockForUpdate()->first();

                if ($cow && $cow->status === Cow::STATUS_SOLD) {
                    $farm = Farm::find($locked->farm_id);

                    if (! $farm->canAddCows()) {
                        throw ValidationException::withMessages([
                            'reason' => 'প্ল্যানের গরুর সীমা শেষ, তাই বিক্রি বাতিল করে গরুটিকে আবার সক্রিয় করা যাচ্ছে না। আগে প্ল্যান আপগ্রেড করুন।',
                        ]);
                    }

                    $cow->forceFill(['status' => Cow::STATUS_ACTIVE, 'exit_date' => null])->save();
                }
            }

            Payment::withoutGlobalScope('farm')
                ->where('payable_type', $locked->getMorphClass())
                ->where('payable_id', $locked->getKey())
                ->where('status', 'active')
                ->update([
                    'status' => 'void',
                    'void_reason' => 'মূল রেকর্ড বাতিল হয়েছে',
                    'voided_by' => $user->id,
                    'voided_at' => now(),
                ]);

            $locked->forceFill([
                'status' => 'void',
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'voided_at' => now(),
            ])->save();

            return true;
        });
    }

    private function applyInitialPayment(Model $record, float $total, array $pay, User $user): void
    {
        $mode = $pay['mode'] ?? 'unpaid';

        if ($mode === 'unpaid') {
            return;
        }

        $amount = $mode === 'paid' ? $total : (float) ($pay['amount'] ?? 0);

        $this->payments->record(
            $record,
            $amount,
            $pay['date'],
            $pay['method'] ?? 'cash',
            null,
            $user
        );
    }
}
