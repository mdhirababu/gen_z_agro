<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function index(Farm $farm)
    {
        Gate::authorize('manageBilling', $farm);

        $farm->load('subscription.plan');

        return view('billing.index', [
            'farm' => $farm,
            'subscription' => $farm->subscription,
            'memberCount' => $farm->users()->count(), // নতুন লাইন
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
            'payments' => $farm->subscriptionPayments()->with('plan')->latest()->get(),
            'hasPending' => $farm->subscriptionPayments()
                ->where('status', SubscriptionPayment::PENDING)->exists(),
        ]);
    }

    public function store(Request $request, Farm $farm)
    {
        Gate::authorize('manageBilling', $farm);

        if ($farm->subscriptionPayments()->where('status', SubscriptionPayment::PENDING)->exists()) {
            return back()->with('error', 'একটি পেমেন্ট ইতিমধ্যে যাচাইয়ের অপেক্ষায় আছে।');
        }

        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'method' => ['required', 'in:bkash,nagad,rocket,bank'],
            'trx_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subscription_payments', 'trx_id')
                    ->where('method', $request->input('method')),
            ],
            'sender_number' => ['nullable', 'string', 'max:30'],
        ]);

        $plan = Plan::where('is_active', true)->findOrFail($data['plan_id']);

        // amount ব্যবহারকারীর কাছ থেকে নয়, প্ল্যানের দাম থেকে বসে
        $farm->subscriptionPayments()->create([
            'subscription_id' => $farm->subscription->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'method' => $data['method'],
            'trx_id' => $data['trx_id'],
            'sender_number' => $data['sender_number'] ?? null,
        ]);

        return back()->with('success', 'পেমেন্টের তথ্য জমা হয়েছে। যাচাই শেষে সাবস্ক্রিপশন চালু হবে।');
    }
}
