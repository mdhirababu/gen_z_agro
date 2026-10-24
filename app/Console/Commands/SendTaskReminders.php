<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Services\SmsService;
use App\Services\TaskService;
use App\Support\ReminderMessage;
use Illuminate\Console\Command;

class SendTaskReminders extends Command
{
    protected $signature = 'sms:send-reminders {--farm= : শুধু একটি খামার (id)} {--dry : SMS না পাঠিয়ে শুধু দেখায়}';

    protected $description = 'আসন্ন কাজের (টিকা, গর্ভ পরীক্ষা, প্রসব) সারসংক্ষেপ SMS পাঠায়: ৩ দিন আগে, দিনেই, ১ দিন দেরিতে';

    public function handle(TaskService $tasks, SmsService $sms): int
    {
        $farms = Farm::query()
            ->where('sms_enabled', true)
            ->when($this->option('farm'), fn($q, $id) => $q->whereKey($id))
            ->with('subscription')
            ->get();

        $today = now()->toDateString();

        foreach ($farms as $farm) {
            try {
                if (! $farm->subscription || ! $farm->subscription->isWritable()) {
                    $this->line("খামার #{$farm->id}: সাবস্ক্রিপশনের মেয়াদ শেষ, বাদ।");
                    continue;
                }

                $number = $farm->sms_phone ?: $farm->phone;

                if (! SmsService::normalize($number)) {
                    $this->warn("খামার #{$farm->id}: সঠিক ফোন নম্বর নেই, বাদ।");
                    continue;
                }

                app()->instance('currentFarm', $farm);

                $due = $tasks->upcoming($farm, 3)
                    ->filter(fn($t) => in_array($t->days(), [3, 0, -1], true))
                    ->values();

                if ($due->isEmpty()) {
                    continue;
                }

                $message = ReminderMessage::build($farm, $due);

                if ($this->option('dry')) {
                    $this->info("খামার #{$farm->id} (পাঠানো হচ্ছে না):");
                    $this->line($message);
                    continue;
                }

                $log = $sms->sendDigest($farm, $number, $message, $today);

                $this->line("খামার #{$farm->id}: {$log->status} (চেষ্টা {$log->attempts})");
            } catch (\Throwable $e) {
                // বার্তায় গোপন তথ্য থাকতে পারে, তাই শুধু ধরনটা দেখানো
                $this->error("খামার #{$farm->id}: ত্রুটি (" . class_basename($e) . ')');
            } finally {
                app()->forgetInstance('currentFarm');
            }
        }

        return self::SUCCESS;
    }
}
