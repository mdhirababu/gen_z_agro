<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /**
     * @return array{ok: bool, response: ?string}
     */
    public function send(string $to, string $message): array;
}
