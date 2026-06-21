<?php

declare(strict_types=1);

namespace AIArmada\Communications\Webhooks\Normalizers;

use AIArmada\Communications\Data\ProviderEventData;
use AIArmada\Communications\Webhooks\Contracts\ProviderEventNormalizer;
use Carbon\CarbonImmutable;

final class NullProviderEventNormalizer implements ProviderEventNormalizer
{
    public function normalize(string $provider, array $payload): ProviderEventData
    {
        return new ProviderEventData(
            provider: $provider,
            providerEventId: $payload['id'] ?? $payload['event_id'] ?? null,
            providerMessageId: $payload['message_id'] ?? $payload['provider_message_id'] ?? null,
            eventType: $payload['event'] ?? $payload['event_type'] ?? $payload['type'] ?? 'unknown',
            occurredAt: CarbonImmutable::now(),
            communicationId: $payload['communication_id'] ?? null,
            deliveryId: $payload['delivery_id'] ?? null,
            payload: $payload,
            failureCode: $payload['failure_code'] ?? $payload['error_code'] ?? null,
            failureMessage: $payload['failure_message'] ?? $payload['error'] ?? $payload['message'] ?? null,
        );
    }

    public function supports(string $provider): bool
    {
        return false;
    }
}
