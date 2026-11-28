<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Contracts;

use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Data\TrackingStatus;
use Carbon\CarbonImmutable;

interface TrackingReader
{
    /**
     * @return array{tracking_number: string, order_id: string|null, current_status: TrackingStatus, events: array<array{status: TrackingStatus, description: string, location: string|null, occurred_at: CarbonImmutable, raw: TrackingDetailData}>}
     */
    public function track(?string $orderId = null, ?string $trackingNumber = null): array;
}
