<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function startTrial(Farm $farm): Subscription
    {
        $plan = Plan::where('slug', config('subscription.trial_plan'))->firstOrFail();

        return Subscription::create([
            'farm_id' => $farm->id,
            'plan_id' => $plan->id,
            'status' => Subscription::TRIAL,
            'trial_ends_at' => now()->addDays((int) config('subscription.trial_days')),
        ]);
    }

    /** পেমেন্ট অনুমোদন করে মেয়াদ বাড়ায়। দ্বিতীয়বার চালালে কিছু হয় না। */
    public function approve(SubscriptionPayment $payment, User $admin): bool
    {
        return DB::transaction(function () use ($payment, $admin) {
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== SubscriptionPayment::PENDING) {
                return false;
            }

            $subscription = Subscription::where('farm_id', $payment->farm_id)
                ->lockForUpdate()
                ->firstOrFail();

            $plan = $payment->plan;

            // একই প্ল্যানে মেয়াদ থাকতে renew করলে বাকি দিনের সঙ্গে যোগ হবে
            $extending = $subscription->ends_at
                && $subscription->ends_at->isFuture()
                && $subscription->plan_id === $plan->id;

            $base = $extending ? $subscription->ends_at : now();

            $subscription->update([
                'plan_id' => $plan->id,
                'status' => Subscription::ACTIVE,
                'starts_at' => $extending ? $subscription->starts_at : now(),
                'ends_at' => $base->copy()->addDays($plan->duration_days),
                'cancelled_at' => null,
            ]);

            $payment->forceFill([
                'status' => SubscriptionPayment::APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return true;
        });
    }

    public function reject(SubscriptionPayment $payment, User $admin, ?string $note = null): bool
    {
        return DB::transaction(function () use ($payment, $admin, $note) {
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== SubscriptionPayment::PENDING) {
                return false;
            }

            $payment->forceFill([
                'status' => SubscriptionPayment::REJECTED,
                'note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return true;
        });
    }
}
