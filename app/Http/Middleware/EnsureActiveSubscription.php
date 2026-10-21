<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $farm = $request->attributes->get('currentFarm');
        $farm->loadMissing('subscription.plan');

        $subscription = $farm->subscription;
        $status = $subscription?->currentStatus() ?? Subscription::EXPIRED;

        view()->share('subscription', $subscription);
        view()->share('subscriptionStatus', $status);
        view()->share('subscriptionDaysLeft', $subscription?->daysLeft());

        $writable = in_array(
            $status,
            [Subscription::TRIAL, Subscription::ACTIVE, Subscription::PAST_DUE],
            true
        );

        // মেয়াদ শেষ হলে খামার read-only: দেখা যাবে, নতুন এন্ট্রি বা পরিবর্তন যাবে না
        if (
            ! $writable
            && ! $request->isMethodSafe()
            && ! $request->routeIs('farms.billing.*')
            && ! $request->user()->is_super_admin
        ) {
            return back()->with('error', 'সাবস্ক্রিপশনের মেয়াদ শেষ। খামারটি এখন শুধু দেখা যাবে। নবায়ন করলে আবার সব চালু হবে।');
        }

        return $next($request);
    }
}
