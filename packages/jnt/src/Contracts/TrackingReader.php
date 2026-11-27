<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Contracts;

interface TrackingReader
{
    /**
     * @return array{tracking_number: string, order_id: string|null, current_status: \AIArmada\Jnt\Enums\TrackingStatus, events: array<array{status: \AIArmada\Jnt\Enums\TrackingStatus, description: string, location: string|null, occurred_at: \Carbon\CarbonImmutable, raw: \AIArmada\Jnt\Data\TrackingDetailData}>}
     */
    public function track(?string $orderId = null, ?string $trackingNumber = null): array;
}
