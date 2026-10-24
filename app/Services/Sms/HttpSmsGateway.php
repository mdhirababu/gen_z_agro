<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

class HttpSmsGateway implements SmsGateway
{
    public function __construct(private array $config) {}

    public function send(string $to, string $message): array
    {
        $url = $this->config['url'] ?? null;

        if (! $url) {
            return ['ok' => false, 'response' => 'SMS_HTTP_URL ঠিক করা নেই'];
        }

        $params = array_merge($this->config['static'] ?? [], [
            ($this->config['fields']['to'] ?? 'number') => $to,
            ($this->config['fields']['message'] ?? 'message') => $message,
        ]);

        $client = Http::timeout((int) ($this->config['timeout'] ?? 15));
        $method = strtoupper($this->config['method'] ?? 'POST');
        $format = $this->config['body_format'] ?? 'form';

        $response = match (true) {
            $method === 'GET' => $client->get($url, $params),
            $format === 'json' => $client->asJson()->post($url, $params),
            default => $client->asForm()->post($url, $params),
        };

        $body = $response->body();
        $needle = $this->config['success_contains'] ?? null;

        $ok = $response->successful() && (! $needle || str_contains($body, $needle));

        return ['ok' => $ok, 'response' => mb_substr($body, 0, 500)];
    }
}
