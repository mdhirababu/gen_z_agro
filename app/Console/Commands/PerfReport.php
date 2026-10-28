<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PerfReport extends Command
{
    protected $signature = 'perf:report {farm : খামারের id} {--period=this_year : today, this_week, this_month, last_month, this_year, fiscal_year}';

    protected $description = 'রিপোর্টের হিসাব কত সময় ও কয়টা query নেয়, তা মাপে (শুধু পড়ে, কিছু বদলায় না)';

    public function handle(ReportService $reports): int
    {
        $farm = Farm::withTrashed()->findOrFail($this->argument('farm'));
        app()->instance('currentFarm', $farm);

        $period = ReportPeriod::make((string) $this->option('period'));

        $this->line('খামার: ' . $farm->name . ' | সময়কাল: ' . $period->label);
        $this->line('গরু: ' . DB::table('cows')->where('farm_id', $farm->id)->count()
            . ' | দুধের রেকর্ড: ' . DB::table('milk_records')->where('farm_id', $farm->id)->count()
            . ' | বিক্রি: ' . DB::table('sales')->where('farm_id', $farm->id)->count()
            . ' | খরচ: ' . DB::table('expenses')->where('farm_id', $farm->id)->count());

        $rows = [];

        foreach (['cashFlow', 'profitLoss', 'cowProfitability'] as $method) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $started = microtime(true);
            $reports->{$method}($farm, $period);
            $ms = (microtime(true) - $started) * 1000;

            $log = DB::getQueryLog();
            DB::disableQueryLog();

            $rows[] = [
                $method,
                round($ms) . ' ms',
                count($log),
                round((float) collect($log)->max('time'), 1) . ' ms',
            ];
        }

        $this->table(['রিপোর্ট', 'মোট সময়', 'query', 'সবচেয়ে ধীর query'], $rows);
        $this->line('পরামর্শ: কোনো রিপোর্ট ১ সেকেন্ডের বেশি নিলে আমাকে এই টেবিল ও খামারের মাপ পাঠিও।');

        return self::SUCCESS;
    }
}
