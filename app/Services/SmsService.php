<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\SmsLog;
use App\Services\Sms\SmsGateway;

class SmsService
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(private SmsGateway $gateway) {}

    /** 01XXXXXXXXX, নম্বর সঠিক না হলে null */
    public static function local(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);
        }

        return preg_match('/^01[3-9]\d{8}$/', $digits) ? $digits : null;
    }

    /** 8801XXXXXXXXX, নম্বর সঠিক না হলে null */
    public static function normalize(?string $number): ?string
    {
        $local = self::local($number);

        return $local ? '88' . $local : null;
    }

    /** বার্তা কয় segment: ইংরেজিতে ১৬০ (জোড়া হলে ১৫৩), বাংলায় ৭০ (জোড়া হলে ৬৭) */
    public static function segments(string $message): int
    {
        $length = mb_strlen($message);

        if (preg_match('/^[\x20-\x7E]*$/', $message)) {
            return $length <= 160 ? 1 : (int) ceil($length / 153);
        }

        return $length <= 70 ? 1 : (int) ceil($length / 67);
    }

    public function usedSegments(Farm|int $farm): int
    {
        $id = $farm instanceof Farm ? $farm->id : $farm;

        return (int) SmsLog::where('farm_id', $id)
            ->where('status', 'sent')
            ->whereDate('created_at', '>=', now()->startOfMonth()->toDateString())
            ->sum('segments');
    }

    /** দিনে একবারই। ব্যর্থ হলে সর্বোচ্চ MAX_ATTEMPTS বার চেষ্টা। */
    public function sendDigest(Farm $farm, string $rawNumber, string $message, string $forDate): SmsLog
    {
        $log = SmsLog::where('farm_id', $farm->id)
            ->where('kind', 'task_digest')
            ->whereDate('for_date', $forDate)
            ->first();

        if ($log) {
            if (in_array($log->status, ['sent', 'skipped'], true) || $log->attempts >= self::MAX_ATTEMPTS) {
                return $log;
            }
        } else {
            $log = new SmsLog();
            $log->forceFill(['status' => 'queued', 'attempts' => 0]);
        }

        $log->forceFill([
            'farm_id' => $farm->id,
            'phone' => self::local($rawNumber) ?? $rawNumber,
            'kind' => 'task_digest',
            'for_date' => $forDate,
            'message' => $message,
            'segments' => self::segments($message),
        ])->save();

        return $this->deliver($log);
    }

    public function sendTest(Farm $farm, string $rawNumber): SmsLog
    {
        $message = 'Gen Z Agro: এটি একটি পরীক্ষামূলক SMS। আপনার খামারের বিজ্ঞপ্তি ঠিকমতো কাজ করছে।';

        $log = new SmsLog();
        $log->forceFill([
            'farm_id' => $farm->id,
            'phone' => self::local($rawNumber) ?? $rawNumber,
            'kind' => 'test',
            'for_date' => null,
            'message' => $message,
            'segments' => self::segments($message),
            'status' => 'queued',
            'attempts' => 0,
        ])->save();

        return $this->deliver($log);
    }

    private function deliver(SmsLog $log): SmsLog
    {
        $limit = (int) config('sms.monthly_segment_limit');

        if ($limit > 0 && $this->usedSegments((int) $log->farm_id) + (int) $log->segments > $limit) {
            $log->forceFill(['status' => 'skipped', 'error' => 'মাসের SMS সীমা শেষ'])->save();

            return $log;
        }

        $log->attempts = (int) $log->attempts + 1;

        $target = config('sms.number_format') === 'local' ? self::local($log->phone) : self::normalize($log->phone);

        if (! $target) {
            $log->forceFill(['status' => 'failed', 'error' => 'ফোন নম্বর সঠিক নয়'])->save();

            return $log;
        }

        try {
            $result = $this->gateway->send($target, $log->message);
            $ok = (bool) ($result['ok'] ?? false);

            $log->forceFill([
                'status' => $ok ? 'sent' : 'failed',
                'provider_response' => $result['response'] ?? null,
                'error' => $ok ? null : 'গেটওয়ে সফল বলেনি',
                'sent_at' => $ok ? now() : null,
            ]);
        } catch (\Throwable $e) {
            // exception-এর লেখায় URL বা API key থাকতে পারে, তাই শুধু ধরনটা রাখা হয়
            $log->forceFill([
                'status' => 'failed',
                'error' => 'সংযোগ বা API ত্রুটি (' . class_basename($e) . ')',
            ]);
        }

        $log->save();

        return $log;
    }
}
