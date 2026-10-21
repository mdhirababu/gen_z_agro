<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function storeForExpense(Request $request, Farm $farm, Expense $expense, PaymentService $payments)
    {
        Gate::authorize('manageFinance', $farm);
        $data = $this->validated($request);

        $payments->record($expense, (float) $data['amount'], $data['paid_at'], $data['method'], $data['note'] ?? null, $request->user());

        return back()->with('success', 'পরিশোধ নথিভুক্ত হয়েছে।');
    }

    public function storeForSale(Request $request, Farm $farm, Sale $sale, PaymentService $payments)
    {
        Gate::authorize('manageFinance', $farm);
        $data = $this->validated($request);

        $payments->record($sale, (float) $data['amount'], $data['paid_at'], $data['method'], $data['note'] ?? null, $request->user());

        return back()->with('success', 'আদায় নথিভুক্ত হয়েছে।');
    }

    /** ক্রেতার কাছ থেকে একবারে টাকা নেওয়া, পুরোনো বিক্রি আগে মেটে */
    public function receiveForCustomer(Request $request, Farm $farm, Customer $customer, PaymentService $payments)
    {
        Gate::authorize('manageFinance', $farm);
        $data = $this->validated($request);

        $count = $payments->receiveForCustomer(
            $customer,
            (float) $data['amount'],
            $data['paid_at'],
            $data['method'],
            $data['note'] ?? null,
            $request->user()
        );

        return back()->with('success', 'টাকা আদায় নথিভুক্ত হয়েছে, ' . $count . 'টি বিক্রির বকেয়ায় বসেছে।');
    }

    public function void(Request $request, Farm $farm, Payment $payment, PaymentService $payments)
    {
        Gate::authorize('manageFinance', $farm);

        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:3', 'max:255']],
            [],
            ['reason' => 'বাতিলের কারণ']
        );

        $done = $payments->void($payment, $request->user(), $data['reason']);

        return back()->with($done ? 'success' : 'error', $done ? 'পেমেন্টটি বাতিল করা হয়েছে।' : 'পেমেন্টটি আগেই বাতিল করা হয়েছে।');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(config('farm.payment_methods')))],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['amount' => 'পরিমাণ', 'paid_at' => 'তারিখ', 'method' => 'মাধ্যম']);
    }
}
