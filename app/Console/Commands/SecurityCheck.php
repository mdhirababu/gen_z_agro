<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReportPdf;
use Illuminate\Console\Command;

class SecurityCheck extends Command
{
    protected $signature = 'security:check {--strict : সতর্কতাকেও ব্যর্থ ধরবে}';

    protected $description = 'প্রোডাকশনে তোলার আগে নিরাপত্তা-সেটিং যাচাই করে';

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

        if ($fails > 0 || ($this->option('strict') && $warnings > 0)) {
            $this->error('প্রোডাকশনে তোলার আগে ওপরের ✗ চিহ্নিত বিষয়গুলো ঠিক করুন।');

            return self::FAILURE;
        }

        $this->info('জরুরি কোনো সমস্যা পাওয়া যায়নি।');

        return self::SUCCESS;
    }

    private function checks(): array
    {
        $list = [];

        $add = function (bool $ok, string $okText, string $badText, string $fix, string $severity = 'fail') use (&$list) {
            $list[] = ['ok' => $ok, 'text' => $ok ? $okText : $badText, 'fix' => $ok ? '' : $fix, 'severity' => $severity];
        };

        $add(config('app.env') === 'production', 'APP_ENV=production', 'APP_ENV production নয় (এখন: ' . config('app.env') . ')', '.env-এ APP_ENV=production দিন');
        $add(config('app.debug') === false, 'APP_DEBUG বন্ধ আছে', 'APP_DEBUG চালু আছে, ত্রুটির পাতায় গোপন তথ্য দেখা যায়', '.env-এ APP_DEBUG=false দিন');
        $add(filled(config('app.key')), 'APP_KEY বসানো আছে', 'APP_KEY নেই', 'php artisan key:generate চালান');
        $add(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL https', 'APP_URL https দিয়ে শুরু নয়', '.env-এ APP_URL=https://আপনার-ডোমেইন দিন');
        $add(config('session.secure') === true, 'কুকি শুধু HTTPS-এ যায়', 'SESSION_SECURE_COOKIE চালু নয়', '.env-এ SESSION_SECURE_COOKIE=true দিন');
        $add(config('session.http_only') !== false, 'কুকি JavaScript থেকে লুকানো', 'session.http_only বন্ধ', 'config/session.php-তে http_only => true রাখুন');
        $add(in_array(config('session.same_site'), ['lax', 'strict'], true), 'কুকির same_site ঠিক আছে', 'session.same_site লিখা নেই', '.env-এ SESSION_SAME_SITE=lax দিন', 'warn');
        $add((bool) config('audit.enabled'), 'অডিট লগ চালু', 'অডিট লগ বন্ধ', '.env-এ AUDIT_ENABLED=true দিন');
        $add(! is_file(public_path('.env')), 'public ফোল্ডারে .env নেই', 'public ফোল্ডারে .env ফাইল পাওয়া গেছে', 'ওয়েব রুট শুধু public ফোল্ডার হতে হবে, .env সরিয়ে ফেলুন');
        $add(
            ! class_exists('Barryvdh\Debugbar\ServiceProvider') && ! class_exists('Laravel\Telescope\TelescopeServiceProvider'),
            'debug প্যাকেজ নেই',
            'Debugbar বা Telescope ইনস্টল করা আছে',
            'composer install --no-dev দিয়ে প্রোডাকশনে ইনস্টল করুন'
        );

        $add(! in_array(config('mail.default'), ['log', 'array'], true), 'মেইল ড্রাইভার আসল', 'মেইল log বা array, পাসওয়ার্ড রিসেটের ইমেইল কারো কাছে যাবে না', '.env-এ আসল SMTP সেটিং দিন', 'warn');
        $add(config('sms.driver') !== 'log', 'SMS ড্রাইভার আসল', 'SMS ড্রাইভার log, SMS আসলে যাচ্ছে না', '.env-এ SMS_DRIVER=http ও গেটওয়ের সেটিং দিন', 'warn');
        $add((bool) config('security.headers'), 'security header চালু', 'security header বন্ধ', '.env-এ SECURITY_HEADERS=true দিন', 'warn');
        $add(config('security.csp_mode') === 'enforce', 'CSP আটকাচ্ছে (enforce)', 'CSP এখনো enforce নয় (এখন: ' . config('security.csp_mode') . ')', 'console-এ সতর্কতা না থাকলে .env-এ CSP_MODE=enforce দিন', 'warn');
        $add((bool) config('security.hsts'), 'HSTS চালু', 'HSTS বন্ধ', '.env-এ HSTS_ENABLED=true দিন (HTTPS ঠিকমতো চললে)', 'warn');
        $add(is_link(public_path('storage')) || is_dir(public_path('storage')), 'storage লিঙ্ক আছে', 'public/storage নেই, গরুর ছবি দেখা যাবে না', 'php artisan storage:link চালান', 'warn');
        $add(app(ReportPdf::class)->fontsReady(), 'PDF-এর ফন্ট আছে', 'PDF-এর ফন্ট নেই', 'storage/fonts-এ Hind Siliguri-র দুটো ফাইল রাখুন', 'warn');
        $add(app()->configurationIsCached(), 'config ক্যাশ করা আছে', 'config ক্যাশ করা নেই', 'php artisan config:cache চালান', 'warn');
        $placeholders = collect(['privacy', 'terms'])->contains(
            fn($view) => (bool) preg_match('/\[\[[^\]]+\]\]/u', (string) @file_get_contents(resource_path("views/legal/{$view}.blade.php")))
        );
        $add(! $placeholders, 'গোপনীয়তা নীতি ও শর্তাবলীর ফাঁকা ঘর পূরণ করা', 'গোপনীয়তা নীতি বা শর্তাবলীতে [[...]] ফাঁকা ঘর রয়ে গেছে', 'resources/views/legal ফোল্ডারের ফাইলে [[...]] অংশগুলো আসল তথ্য দিয়ে ভরুন, আর আইনজীবীকে দিয়ে দেখিয়ে নিন', 'warn');
        $add(filled(config('backup.backup.password')), 'ব্যাকআপ এনক্রিপ্ট করা (পাসওয়ার্ড আছে)', 'ব্যাকআপে পাসওয়ার্ড নেই', '.env-এ BACKUP_ARCHIVE_PASSWORD দিন', 'warn');
        $admins = User::query()->where('is_super_admin', true)->count();
        $add($admins >= 1 && $admins <= 3, "সুপার অ্যাডমিন {$admins} জন", "সুপার অ্যাডমিন {$admins} জন", 'সুপার অ্যাডমিন যত কম রাখা যায় ততই ভালো (১ থেকে ৩ জন)', 'warn');

        return $list;
    }
}
