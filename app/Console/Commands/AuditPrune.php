<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditPrune extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'মেয়াদ পার হওয়া অডিট লগ সরায় (config/audit.php-র retention_months অনুযায়ী)';

    public function handle(): int
    {
        $months = (int) config('audit.retention_months');

        if ($months <= 0) {
            $this->info('অডিট লগ চিরকাল রাখার সেটিং আছে, কিছু সরানো হয়নি।');

            return self::SUCCESS;
        }

        $cutoff = now()->subMonths($months);
        $total = 0;

        // মডেলের "মোছা যায় না" নিয়ম ইচ্ছে করে এড়িয়ে শুধু এখানে query builder দিয়ে, ছোট ছোট ভাগে
        do {
            $deleted = DB::table('audit_logs')->where('created_at', '<', $cutoff)->limit(1000)->delete();
            $total += $deleted;
        } while ($deleted === 1000);

        $this->info("{$total}টি পুরোনো লগ সরানো হয়েছে (জমা রাখা হয় {$months} মাস)।");

        return self::SUCCESS;
    }
}
