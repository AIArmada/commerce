<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Webhooks\Actions;

use AIArmada\Affiliates\Contracts\AffiliateWebhookTransport;
use AIArmada\Affiliates\Data\AffiliateWebhookRequest;
use AIArmada\Affiliates\Data\AffiliateWebhookResult;
use AIArmada\Affiliates\Features\Webhooks\Models\AffiliateWebhookDelivery;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class DeliverAffiliateWebhook
{
    public function __construct(private AffiliateWebhookTransport $transport) {}

    public function execute(string $deliveryId): void
    {
        $delivery = $this->claim($deliveryId);

        if ($delivery === null) {
            return;
        }

        $headers = [];
        foreach ($delivery->headers ?? [] as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $headers[$key] = (string) $value;
            }
        }

        $result = ! config('affiliates.events.dispatch_webhooks', false)
            ? new AffiliateWebhookResult(false, null, 'webhooks_disabled')
            : $this->transport->send(new AffiliateWebhookRequest($delivery->endpoint, $headers, $delivery->body_json, $delivery->signature));

        $updated = AffiliateWebhookDelivery::query()->whereKey($deliveryId)
            ->where('status', 'processing')->where('attempt_count', $delivery->attempt_count)
            ->update([
                'status' => $result->successful ? 'sent' : 'failed',
                'leased_at' => null,
                'response_status' => $result->status,
                'last_error_code' => $result->successful ? null : $result->errorCode,
                'sent_at' => $result->successful ? CarbonImmutable::now() : null,
            ]);

        if ($updated === 1 && ! $result->successful) {
            throw new RuntimeException('Affiliate webhook delivery failed.');
        }
    }

    public function markDead(string $deliveryId): void
    {
        AffiliateWebhookDelivery::query()->whereKey($deliveryId)->whereIn('status', ['pending', 'failed'])->update([
            'status' => 'dead', 'dead_at' => CarbonImmutable::now(), 'leased_at' => null,
        ]);
    }

    private function claim(string $deliveryId): ?AffiliateWebhookDelivery
    {
        return (new AffiliateWebhookDelivery)->getConnection()->transaction(function () use ($deliveryId): ?AffiliateWebhookDelivery {
            $delivery = AffiliateWebhookDelivery::query()->lockForUpdate()->find($deliveryId);

            if ($delivery === null || in_array($delivery->status, ['sent', 'dead'], true)) {
                return null;
            }

            $leaseSeconds = max(30, (int) config('affiliates.webhooks.delivery.lease_seconds', 120), (int) config('affiliates.webhooks.delivery.timeout_seconds', 10) + 10);

            if ($delivery->status === 'processing' && $delivery->leased_at?->isAfter(CarbonImmutable::now()->subSeconds($leaseSeconds))) {
                return null;
            }

            if ($delivery->attempt_count >= $delivery->max_attempts) {
                $delivery->forceFill(['status' => 'dead', 'dead_at' => CarbonImmutable::now(), 'leased_at' => null])->save();

                return null;
            }

            $delivery->forceFill([
                'status' => 'processing',
                'attempt_count' => $delivery->attempt_count + 1,
                'leased_at' => CarbonImmutable::now(),
                'last_attempt_at' => CarbonImmutable::now(),
            ])->save();

            return $delivery;
        }, attempts: 3);
    }
}
