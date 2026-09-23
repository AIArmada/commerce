<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Merchant;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Remote-merchant client for the network conversion endpoint.
 *
 * Call {@see report()} when a network-attributed order is paid. Connection
 * details come from `affiliates.merchant` config.
 */
final class NetworkPostbackClient
{
    /**
     * @return array{ok: bool, status: int|null, body: mixed, error: string|null}
     */
    public function report(string $linkCode, string $externalReference, int $revenueMinor, ?string $currency = null): array
    {
        if ($linkCode === '') {
            return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'no network attribution'];
        }

        $base = mb_rtrim((string) config('affiliates.merchant.network_url'), '/');
        $site = (string) config('affiliates.merchant.site');
        $token = (string) config('affiliates.merchant.token');

        if ($base === '' || $site === '' || $token === '') {
            return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'network merchant not configured'];
        }

        $prefix = mb_trim((string) config('affiliates.merchant.prefix', 'api/affiliate-network'), '/');

        try {
            $response = Http::timeout(max(1, (int) config('affiliates.merchant.timeout_seconds', 8)))
                ->withToken($token)
                ->acceptJson()
                ->post($base . '/' . $prefix . '/conversions', array_filter([
                    'site' => $site,
                    'link_code' => $linkCode,
                    'external_reference' => $externalReference,
                    'revenue_minor' => $revenueMinor,
                    'currency' => $currency,
                ], static fn (mixed $value): bool => $value !== null));

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->json(),
                'error' => $response->successful() ? null : 'network responded ' . $response->status(),
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'status' => null, 'body' => null, 'error' => $exception->getMessage()];
        }
    }
}
