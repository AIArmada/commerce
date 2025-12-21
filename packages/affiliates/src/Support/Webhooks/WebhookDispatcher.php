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

        // Block localhost patterns
        if (in_array($host, ['localhost', '0.0.0.0'], true)) {
            return false;
        }

        // Try to resolve to IP address for proper validation
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        // If DNS resolution failed, block for safety
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            // Allow valid domain names that don't resolve yet
            if (! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i', $host)) {
                return false;
            }
            // Accept valid domain format
            return true;
        }

        // Validate IP and check if private/reserved
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
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
