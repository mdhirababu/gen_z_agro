<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Cache;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:sync')->daily();
Schedule::command('sms:send-reminders')->dailyAt('08:00')->timezone('Asia/Dhaka');
Schedule::command('audit:prune')->monthlyOn(1, '03:00')->timezone('Asia/Dhaka');

// cron সত্যিই চলছে কি না ধরার জন্য প্রতি মিনিটে একটা চিহ্ন (ops:check এটা দেখে)
Schedule::call(fn() => Cache::put('scheduler:heartbeat', now()->timestamp, now()->addHour()))
    ->everyMinute()
    ->name('scheduler-heartbeat');

Schedule::command('backup:clean')->dailyAt('01:30')->timezone('Asia/Dhaka');
Schedule::command('backup:run')->dailyAt('02:00')->timezone('Asia/Dhaka');
Schedule::command('backup:monitor')->dailyAt('03:00')->timezone('Asia/Dhaka');
