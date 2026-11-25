<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Network;

use AIArmada\Affiliates\Contracts\NetworkPostbackGateway;
use AIArmada\Affiliates\Data\NetworkPostbackResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Remote-merchant client for the network conversion endpoints.
 *
 * Call {@see report()} when a network-attributed order is paid,
 * {@see adjustConversion()} when a previously reported order changes, and
 * {@see voidConversion()} when it is refunded or voided. Connection
 * details come from `affiliates.merchant` config.
 */
final class HttpNetworkPostbackGateway implements NetworkPostbackGateway
{
    /**
     * @param  array<string, string>  $subIds
     */
    public function report(
        string $linkCode,
        string $externalReference,
        int $revenueMinor,
        CarbonImmutable $networkClickedAt,
        ?CarbonImmutable $engineTouchAt = null,
        ?string $currency = null,
        ?string $couponCode = null,
        string $eventType = 'purchase',
        array $subIds = [],
    ): NetworkPostbackResult {
        if (! $this->hasAttribution($linkCode, $couponCode)) {
            return new NetworkPostbackResult(false, error: 'no network attribution');
        }

        $base = mb_rtrim((string) config('affiliates.merchant.network_url'), '/');
        $site = (string) config('affiliates.merchant.site');
        $token = (string) config('affiliates.merchant.token');

        if (! $this->validBase($base) || $site === '' || $token === '') {
            return new NetworkPostbackResult(false, error: 'network merchant not configured');
        }

        $prefix = mb_trim((string) config('affiliates.merchant.prefix', 'api/affiliate-network'), '/');

        $payload = [
            'site' => $site,
            'link_code' => $linkCode !== '' ? $linkCode : null,
            'coupon_code' => $couponCode,
            'event_type' => $eventType,
            'external_reference' => $externalReference,
            'revenue_minor' => $revenueMinor,
            'currency' => $currency,
            'engine_touch_at' => $engineTouchAt?->toIso8601String(),
            'network_clicked_at' => $networkClickedAt->toIso8601String(),
        ];

        foreach (['sub_id' => ['sub1', 'sub_id'], 'sub_id_2' => ['sub2', 'sub_id_2'], 'sub_id_3' => ['sub3', 'sub_id_3']] as $target => $keys) {
            foreach ($keys as $key) {
                $value = $subIds[$key] ?? null;

                if (is_string($value) && $value !== '') {
                    $payload[$target] = $value;

                    break;
                }
            }
        }

        try {
            $response = Http::connectTimeout(max(1, (int) config('affiliates.merchant.connect_timeout_seconds', 3)))
                ->timeout(max(1, (int) config('affiliates.merchant.timeout_seconds', 8)))
                ->withOptions(['allow_redirects' => false])
                ->withToken($token)
                ->acceptJson()
                ->post($base . '/' . $prefix . '/conversions', $payload);

            $body = $response->json();
            $decision = is_array($body) && is_array($body['decision'] ?? null)
                && is_string($body['decision']['winner'] ?? null)
                && is_string($body['decision']['reason'] ?? null)
                ? ['winner' => $body['decision']['winner'], 'reason' => $body['decision']['reason']]
                : null;

            $ok = $response->successful() && is_array($body) && ($body['ok'] ?? false) === true;

            return new NetworkPostbackResult($ok, $response->status(), decision: $ok ? $decision : null, error: $ok ? null : 'network responded ' . $response->status());
        } catch (Throwable) {
            return new NetworkPostbackResult(false, error: 'network transport failed');
        }
    }

    /**
     * Correct a previously reported conversion (partial refund, price fix).
     * Idempotent on the adjustment reference.
     */
    public function adjustConversion(
        string $linkCode,
        string $externalReference,
        string $adjustmentReference,
        ?int $deltaRevenueMinor = null,
        ?int $newRevenueMinor = null,
        ?string $reason = null,
        ?string $couponCode = null,
        string $eventType = 'purchase',
    ): NetworkPostbackResult {
        if (! $this->hasAttribution($linkCode, $couponCode)) {
            return new NetworkPostbackResult(false, error: 'no network attribution');
        }

        $base = mb_rtrim((string) config('affiliates.merchant.network_url'), '/');
        $site = (string) config('affiliates.merchant.site');
        $token = (string) config('affiliates.merchant.token');

        if (! $this->validBase($base) || $site === '' || $token === '') {
            return new NetworkPostbackResult(false, error: 'network merchant not configured');
        }

        $prefix = mb_trim((string) config('affiliates.merchant.prefix', 'api/affiliate-network'), '/');

        try {
            $response = Http::connectTimeout(max(1, (int) config('affiliates.merchant.connect_timeout_seconds', 3)))
                ->timeout(max(1, (int) config('affiliates.merchant.timeout_seconds', 8)))
                ->withOptions(['allow_redirects' => false])
                ->withToken($token)
                ->acceptJson()
                ->post($base . '/' . $prefix . '/conversions/adjust', array_filter([
                    'site' => $site,
                    'link_code' => $linkCode !== '' ? $linkCode : null,
                    'coupon_code' => $couponCode,
                    'external_reference' => $externalReference,
                    'event_type' => $eventType,
                    'adjustment_reference' => $adjustmentReference,
                    'delta_revenue_minor' => $deltaRevenueMinor,
                    'new_revenue_minor' => $newRevenueMinor,
                    'reason' => $reason,
                ], static fn (mixed $value): bool => $value !== null));

            $body = $response->json();
            $legStatus = is_array($body) && is_string($body['leg_status'] ?? null)
                ? $body['leg_status']
                : null;

            $ok = $response->successful() && is_array($body) && ($body['ok'] ?? false) === true;

            return new NetworkPostbackResult($ok, $response->status(), legStatus: $ok ? $legStatus : null, error: $ok ? null : 'network responded ' . $response->status());
        } catch (Throwable) {
            return new NetworkPostbackResult(false, error: 'network transport failed');
        }
    }

    /**
     * Retract a previously reported conversion (refund/void). Idempotent:
     * repeat calls return the already-reversed state. A 404 means the
     * network has no conversion for that link and reference.
     */
    public function voidConversion(
        string $linkCode,
        string $externalReference,
        ?string $reason = null,
        ?string $couponCode = null,
        string $eventType = 'purchase',
    ): NetworkPostbackResult {
        if (! $this->hasAttribution($linkCode, $couponCode)) {
            return new NetworkPostbackResult(false, error: 'no network attribution');
        }

        $base = mb_rtrim((string) config('affiliates.merchant.network_url'), '/');
        $site = (string) config('affiliates.merchant.site');
        $token = (string) config('affiliates.merchant.token');

        if (! $this->validBase($base) || $site === '' || $token === '') {
            return new NetworkPostbackResult(false, error: 'network merchant not configured');
        }

        $prefix = mb_trim((string) config('affiliates.merchant.prefix', 'api/affiliate-network'), '/');

        try {
            $response = Http::connectTimeout(max(1, (int) config('affiliates.merchant.connect_timeout_seconds', 3)))
                ->timeout(max(1, (int) config('affiliates.merchant.timeout_seconds', 8)))
                ->withOptions(['allow_redirects' => false])
                ->withToken($token)
                ->acceptJson()
                ->post($base . '/' . $prefix . '/conversions/void', array_filter([
                    'site' => $site,
                    'link_code' => $linkCode !== '' ? $linkCode : null,
                    'coupon_code' => $couponCode,
                    'event_type' => $eventType,
                    'external_reference' => $externalReference,
                    'reason' => $reason,
                ], static fn (mixed $value): bool => $value !== null));

            $body = $response->json();
            $legStatus = is_array($body) && is_string($body['leg_status'] ?? null)
                ? $body['leg_status']
                : null;

            $ok = $response->successful() && is_array($body) && ($body['ok'] ?? false) === true;

            return new NetworkPostbackResult($ok, $response->status(), legStatus: $ok ? $legStatus : null, error: $ok ? null : 'network responded ' . $response->status());
        } catch (Throwable) {
            return new NetworkPostbackResult(false, error: 'network transport failed');
        }
    }

    private function validBase(string $base): bool
    {
        $parts = parse_url($base);

        return is_array($parts) && mb_strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && isset($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment']);
    }

    private function hasAttribution(string $linkCode, ?string $couponCode): bool
    {
        return mb_trim($linkCode) !== '' || ($couponCode !== null && mb_trim($couponCode) !== '');
    }
}
