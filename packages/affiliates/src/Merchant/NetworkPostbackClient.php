<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Merchant;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Remote-merchant client for the network conversion endpoints.
 *
 * Call {@see report()} when a network-attributed order is paid and
 * {@see voidConversion()} when it is refunded or voided. Connection
 * details come from `affiliates.merchant` config.
 */
final class NetworkPostbackClient
{
    /**
     * @return array{ok: bool, status: int|null, body: mixed, decision: array{winner: string, reason: string}|null, error: string|null}
     */
    public function report(
        string $linkCode,
        string $externalReference,
        int $revenueMinor,
        CarbonImmutable $networkClickedAt,
        ?CarbonImmutable $engineTouchAt = null,
        ?string $currency = null,
    ): array {
        if ($linkCode === '') {
            return ['ok' => false, 'status' => null, 'body' => null, 'decision' => null, 'error' => 'no network attribution'];
        }

        $base = mb_rtrim((string) config('affiliates.merchant.network_url'), '/');
        $site = (string) config('affiliates.merchant.site');
        $token = (string) config('affiliates.merchant.token');

        if ($base === '' || $site === '' || $token === '') {
            return ['ok' => false, 'status' => null, 'body' => null, 'decision' => null, 'error' => 'network merchant not configured'];
        }

        $prefix = mb_trim((string) config('affiliates.merchant.prefix', 'api/affiliate-network'), '/');

        try {
            $response = Http::connectTimeout(max(1, (int) config('affiliates.merchant.connect_timeout_seconds', 3)))
                ->timeout(max(1, (int) config('affiliates.merchant.timeout_seconds', 8)))
                ->withToken($token)
                ->acceptJson()
                ->post($base . '/' . $prefix . '/conversions', [
                    'site' => $site,
                    'link_code' => $linkCode,
                    'external_reference' => $externalReference,
                    'revenue_minor' => $revenueMinor,
                    'currency' => $currency,
                    'engine_touch_at' => $engineTouchAt?->toIso8601String(),
                    'network_clicked_at' => $networkClickedAt->toIso8601String(),
                ]);

            $body = $response->json();
            $decision = is_array($body) && is_array($body['decision'] ?? null)
                && is_string($body['decision']['winner'] ?? null)
                && is_string($body['decision']['reason'] ?? null)
                ? ['winner' => $body['decision']['winner'], 'reason' => $body['decision']['reason']]
                : null;

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $body,
                'decision' => $decision,
                'error' => $response->successful() ? null : 'network responded ' . $response->status(),
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'status' => null, 'body' => null, 'decision' => null, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Retract a previously reported conversion (refund/void). Idempotent:
     * repeat calls return the already-reversed state. A 404 means the
     * network has no conversion for that link and reference.
     *
     * @return array{ok: bool, status: int|null, body: mixed, leg_status: string|null, error: string|null}
     */
    public function voidConversion(
        string $linkCode,
        string $externalReference,
        ?string $reason = null,
    ): array {
        if ($linkCode === '') {
            return ['ok' => false, 'status' => null, 'body' => null, 'leg_status' => null, 'error' => 'no network attribution'];
        }

        $base = mb_rtrim((string) config('affiliates.merchant.network_url'), '/');
        $site = (string) config('affiliates.merchant.site');
        $token = (string) config('affiliates.merchant.token');

        if ($base === '' || $site === '' || $token === '') {
            return ['ok' => false, 'status' => null, 'body' => null, 'leg_status' => null, 'error' => 'network merchant not configured'];
        }

        $prefix = mb_trim((string) config('affiliates.merchant.prefix', 'api/affiliate-network'), '/');

        try {
            $response = Http::connectTimeout(max(1, (int) config('affiliates.merchant.connect_timeout_seconds', 3)))
                ->timeout(max(1, (int) config('affiliates.merchant.timeout_seconds', 8)))
                ->withToken($token)
                ->acceptJson()
                ->post($base . '/' . $prefix . '/conversions/void', [
                    'site' => $site,
                    'link_code' => $linkCode,
                    'external_reference' => $externalReference,
                    'reason' => $reason,
                ]);

            $body = $response->json();
            $legStatus = is_array($body) && is_string($body['leg_status'] ?? null)
                ? $body['leg_status']
                : null;

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $body,
                'leg_status' => $legStatus,
                'error' => $response->successful() ? null : 'network responded ' . $response->status(),
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'status' => null, 'body' => null, 'leg_status' => null, 'error' => $exception->getMessage()];
        }
    }
}
