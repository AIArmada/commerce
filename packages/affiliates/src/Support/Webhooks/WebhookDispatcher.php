<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Webhooks;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $type, array $payload): void
    {
        if (! (bool) config('affiliates.events.dispatch_webhooks', false)) {
            return;
        }

        $endpoints = Arr::wrap(config("affiliates.webhooks.endpoints.{$type}", []));
        $headers = (array) config('affiliates.webhooks.headers', []);

        foreach ($endpoints as $url) {
            $trimmed = mb_trim((string) $url);

            if ($trimmed === '') {
                continue;
            }

            if (! $this->isUrlSafe($trimmed)) {
                Log::warning('Affiliates webhook URL rejected for security reasons', [
                    'url' => $trimmed,
                    'type' => $type,
                ]);

                continue;
            }

            $body = [
                'type' => $type,
                'id' => (string) Str::uuid(),
                'data' => $payload,
                'sent_at' => now()->toIso8601String(),
            ];

            $signature = $this->sign($body, $headers['X-Affiliates-Signature'] ?? null);

            try {
                Http::timeout(10)
                    ->withHeaders(array_merge($headers, [
                        'X-Affiliates-Webhook-Signature' => $signature,
                    ]))
                    ->asJson()
                    ->post($trimmed, $body);
            } catch (\Throwable $e) {
                Log::error('Affiliates webhook dispatch failed', [
                    'url' => $trimmed,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function isUrlSafe(string $url): bool
    {
        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme']) || ! isset($parsed['host'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parsed['host']);

        $blockedPatterns = [
            '127.0.0.1',
            'localhost',
            '0.0.0.0',
            '::1',
            '169.254.',
            '10.',
            '192.168.',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (str_starts_with($host, $pattern)) {
                return false;
            }
        }

        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function sign(array $body, ?string $secret): ?string
    {
        $secret ??= config('affiliates.webhooks.headers.X-Affiliates-Signature');

        if (! $secret) {
            return null;
        }

        try {
            $encoded = json_encode($body, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }

        return hash_hmac('sha256', $encoded, $secret);
    }
}
