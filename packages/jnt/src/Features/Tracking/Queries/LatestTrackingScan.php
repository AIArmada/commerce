<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Queries;

use AIArmada\Jnt\Features\Tracking\Api\ReadLatestTrackingScan;
use AIArmada\Jnt\Features\Tracking\Models\JntTrackingEvent;

final readonly class LatestTrackingScan implements ReadLatestTrackingScan
{
    public function forOrder(string $orderId, ?string $ownerType, ?string $ownerId): ?string
    {
        $scan = JntTrackingEvent::query()->withoutOwnerScope()
            ->where('order_id', $orderId)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->max('scan_time');

        return is_string($scan) ? $scan : null;
    }
}
