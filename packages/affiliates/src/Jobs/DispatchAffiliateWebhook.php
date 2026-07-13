<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Jobs;

use AIArmada\Affiliates\Models\AffiliateWebhookDelivery;
use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DispatchAffiliateWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public function __construct(public string $deliveryId)
    {
        $this->tries = max(1, (int) config('affiliates.webhooks.delivery.max_attempts', 5));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        $configured = config('affiliates.webhooks.delivery.backoff_seconds', [10, 30, 120, 300]);

        return is_array($configured)
            ? array_values(array_map('intval', array_filter($configured, 'is_numeric')))
            : [10, 30, 120, 300];
    }

    public function handle(PublicHttpUrlGuard $guard, PinnedHttpClient $client): void
    {
        $delivery = $this->claim();

        if (! $delivery instanceof AffiliateWebhookDelivery) {
            return;
        }

        $responseStatus = null;

        try {
            $target = $guard->validate($delivery->endpoint);
            $headers = is_array($delivery->headers) ? $delivery->headers : [];
            $headers['Accept'] = 'application/json';
            $headers['Content-Type'] = 'application/json';

            if (is_string($delivery->signature) && $delivery->signature !== '') {
                $headers['X-Affiliates-Webhook-Signature'] = $delivery->signature;
            }

            $response = $client->send(
                method: 'POST',
                target: $target,
                options: ['body' => $delivery->body_json],
                headers: $headers,
                connectTimeout: max(1, (int) config('affiliates.webhooks.delivery.connect_timeout_seconds', 3)),
                timeout: max(1, (int) config('affiliates.webhooks.delivery.timeout_seconds', 10)),
            );

            $responseStatus = $response->status();

            if (! $response->successful()) {
                throw new RuntimeException('http_' . $responseStatus);
            }

            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'leased_at' => null,
                'response_status' => $response->status(),
                'last_error_code' => null,
            ])->save();
        } catch (Throwable $throwable) {
            $delivery->forceFill([
                'status' => 'failed',
                'leased_at' => null,
                'response_status' => $responseStatus,
                'last_error_code' => $this->safeErrorCode($throwable),
            ])->save();

            throw new RuntimeException('Affiliate webhook delivery failed.', previous: $throwable);
        }
    }

    public function failed(Throwable $throwable): void
    {
        $delivery = AffiliateWebhookDelivery::query()->find($this->deliveryId);

        if (! $delivery instanceof AffiliateWebhookDelivery || $delivery->status === 'sent') {
            return;
        }

        $delivery->forceFill([
            'status' => 'dead',
            'dead_at' => now(),
            'leased_at' => null,
            'last_error_code' => $delivery->last_error_code ?? $this->safeErrorCode($throwable),
        ])->save();
    }

    private function claim(): ?AffiliateWebhookDelivery
    {
        return DB::transaction(function (): ?AffiliateWebhookDelivery {
            $delivery = AffiliateWebhookDelivery::query()->lockForUpdate()->find($this->deliveryId);

            if (! $delivery instanceof AffiliateWebhookDelivery || in_array($delivery->status, ['sent', 'dead'], true)) {
                return null;
            }

            $leaseSeconds = max(30, (int) config('affiliates.webhooks.delivery.lease_seconds', 120));

            if ($delivery->status === 'processing' && $delivery->leased_at?->isAfter(now()->subSeconds($leaseSeconds))) {
                return null;
            }

            if ($delivery->attempt_count >= $delivery->max_attempts) {
                $delivery->forceFill(['status' => 'dead', 'dead_at' => now(), 'leased_at' => null])->save();

                return null;
            }

            $delivery->forceFill([
                'status' => 'processing',
                'attempt_count' => $delivery->attempt_count + 1,
                'leased_at' => now(),
                'last_attempt_at' => now(),
            ])->save();

            return $delivery;
        }, attempts: 3);
    }

    private function safeErrorCode(Throwable $throwable): string
    {
        $message = mb_strtolower($throwable->getMessage());

        return match (true) {
            str_contains($message, 'http_') => mb_substr($message, 0, 64),
            str_contains($message, 'resolve'), str_contains($message, 'public') => 'unsafe_destination',
            str_contains($message, 'timeout'), str_contains($message, 'timed out') => 'timeout',
            default => 'transport_error',
        };
    }
}
