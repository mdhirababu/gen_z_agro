<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): array
    {
        Log::info('SMS (log driver, আসলে পাঠানো হয়নি)', ['to' => $to, 'message' => $message]);

        return ['ok' => true, 'response' => 'log driver'];
    }
}
