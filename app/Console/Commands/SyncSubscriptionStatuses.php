<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class SyncSubscriptionStatuses extends Command
{
    protected $signature = 'subscriptions:sync';

    protected $description = 'মেয়াদ অনুযায়ী subscription-এর status আপডেট করে';

    public function handle(): int
    {
        $updated = 0;

        Subscription::whereIn('status', [
            Subscription::TRIAL,
            Subscription::ACTIVE,
            Subscription::PAST_DUE,
        ])->each(function (Subscription $subscription) use (&$updated) {
            $current = $subscription->currentStatus();

            if ($current !== $subscription->status) {
                $subscription->update(['status' => $current]);
                $updated++;
            }
        });

        $this->info("{$updated}টি subscription আপডেট হয়েছে।");

        return self::SUCCESS;
    }
}
