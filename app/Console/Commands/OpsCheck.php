<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class OpsCheck extends Command
{
    protected $signature = 'ops:check {--strict : সতর্কতাকেও ব্যর্থ ধরবে}';

    protected $description = 'চালু সার্ভারের স্বাস্থ্য যাচাই: ডাটাবেস, ক্যাশ, ডিস্ক, scheduler (cron), ব্যাকআপ';

    public function handle(): int
    {
        $rows = [];
        $fails = 0;
        $warnings = 0;

        foreach ($this->checks() as $check) {
            if ($check['ok']) {
                $mark = '✓';
            } elseif ($check['severity'] === 'fail') {
                $mark = '✗';
                $fails++;
            } else {
                $mark = '!';
                $warnings++;
            }

            $rows[] = [$mark, $check['text'], $check['fix']];
        }

        $this->table(['', 'যাচাই', 'কী করতে হবে'], $rows);
        $this->line("ব্যর্থ: {$fails}, সতর্কতা: {$warnings}");

        return ($fails > 0 || ($this->option('strict') && $warnings > 0)) ? self::FAILURE : self::SUCCESS;
    }

    private function checks(): array
    {
        $list = [];

        $add = function (bool $ok, string $okText, string $badText, string $fix, string $severity = 'fail') use (&$list) {
            $list[] = ['ok' => $ok, 'text' => $ok ? $okText : $badText, 'fix' => $ok ? '' : $fix, 'severity' => $severity];
        };

        try {
            DB::select('select 1');
            $db = true;
        } catch (\Throwable) {
            $db = false;
        }
        $add($db, 'ডাটাবেস সংযোগ ঠিক আছে', 'ডাটাবেসে সংযোগ হচ্ছে না', '.env-এর DB_* সেটিং ও ডাটাবেস সার্ভার দেখুন');

        try {
            Cache::put('ops:ping', 'ok', 30);
            $cache = Cache::get('ops:ping') === 'ok';
        } catch (\Throwable) {
            $cache = false;
        }
        $add($cache, 'ক্যাশ কাজ করছে', 'ক্যাশ কাজ করছে না', 'CACHE_STORE ও তার সংযোগ দেখুন');

        $add(
            is_writable(storage_path('logs')) && is_writable(storage_path('app')),
            'storage ফোল্ডারে লেখা যায়',
            'storage/logs বা storage/app-এ লেখা যায় না',
            'ফোল্ডারের মালিক ও অনুমতি ঠিক করুন (যেমন chown -R www-data storage)'
        );

        $freeGb = (float) @disk_free_space(storage_path()) / 1024 ** 3;
        $add(
            $freeGb >= (float) config('ops.min_free_gb', 1),
            sprintf('ডিস্কে %.1f GB খালি', $freeGb),
            sprintf('ডিস্কে মাত্র %.1f GB খালি', $freeGb),
            'পুরোনো লগ ও ব্যাকআপ পরিষ্কার করুন বা ডিস্ক বাড়ান',
            'warn'
        );

        $beat = Cache::get('scheduler:heartbeat');
        $fresh = $beat && (now()->timestamp - (int) $beat) <= (int) config('ops.heartbeat_max_minutes', 5) * 60;
        $add(
            (bool) $fresh,
            'scheduler চলছে',
            'scheduler চলছে না (cron ঠিক আছে?)',
            'crontab-এ `* * * * * cd /path/to/app && php artisan schedule:run` আছে কি না দেখুন। এটা বন্ধ থাকলে SMS ও সাবস্ক্রিপশনের কাজ আটকে যায়'
        );

        $age = $this->backupAgeHours();
        if ($age === null) {
            $add(false, '', 'কোনো ব্যাকআপ পাওয়া যায়নি', '`php artisan backup:run` চালান ও schedule ঠিক আছে কি না দেখুন');
        } else {
            $add(
                $age <= (int) config('ops.backup_max_hours', 36),
                sprintf('ব্যাকআপ আছে (সবচেয়ে নতুনটা %.0f ঘণ্টা আগের)', $age),
                sprintf('ব্যাকআপ পুরোনো (সবচেয়ে নতুনটা %.0f ঘণ্টা আগের)', $age),
                '`php artisan backup:run` চালান, ব্যর্থ হলে storage/logs দেখুন'
            );
        }

        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $add($failedJobs === 0, 'ব্যর্থ কাজ নেই', "{$failedJobs}টি কাজ ব্যর্থ হয়ে আছে", '`php artisan queue:failed` দেখুন', 'warn');

        return $list;
    }

    /** সবচেয়ে নতুন ব্যাকআপ ZIP কত ঘণ্টা আগের, না থাকলে null */
    private function backupAgeHours(): ?float
    {
        $disks = (array) config('backup.backup.destination.disks', ['local']);
        $disk = $disks[0] ?? 'local';
        $name = (string) config('backup.backup.name', config('app.name'));

        try {
            $newest = collect(Storage::disk($disk)->files($name))
                ->filter(fn($file) => str_ends_with($file, '.zip'))
                ->map(fn($file) => Storage::disk($disk)->lastModified($file))
                ->max();
        } catch (\Throwable) {
            return null;
        }

        return $newest ? (now()->timestamp - $newest) / 3600 : null;
    }
}
