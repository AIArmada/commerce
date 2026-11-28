<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Api;

interface TrackingDeliveryEvents
{
    public function assertAccessible(string $deliveryId): void;

    /** @param array<string, mixed>|null $metadata */
    public function record(string $deliveryId, string $interaction, ?array $metadata): string;
}
