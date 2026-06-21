<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderEventData
{
    public function __construct(
        public string $provider,
        public ?string $providerEventId,
        public ?string $providerMessageId,
        public string $eventType,
        public CarbonImmutable $occurredAt,
        public ?string $communicationId = null,
        public ?string $deliveryId = null,
        public array $payload = [],
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}
}
