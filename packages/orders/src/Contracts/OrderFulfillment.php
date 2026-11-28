<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderFulfillment
{
    /** @param array<string, mixed> $metadata */
    public function ship(string $orderId, string $carrier, string $trackingNumber, ?string $shipmentId = null, array $metadata = []): void;

    /** @param array<string, mixed> $metadata */
    public function confirmDelivery(string $orderId, array $metadata = []): void;

    /** @param array<string, mixed> $metadata */
    public function complete(string $orderId, array $metadata = []): void;

    public function confirmFree(string $orderId): void;

    public function cancel(string $orderId, string $reason, ?string $canceledBy = null): void;

    public function hold(string $orderId, string $reason, ?string $heldBy = null): void;

    public function releaseHold(string $orderId, ?string $reason = null, ?string $releasedBy = null): void;

    public function flagFraud(string $orderId, string $reason, ?string $flaggedBy = null): void;

    public function returnOrder(string $orderId, ?string $reason = null, ?string $returnedBy = null): void;
}
