<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Persistence;

use AIArmada\Jnt\Features\Tracking\Api\DeleteOrderTracking;
use AIArmada\Jnt\Features\Tracking\Models\JntTrackingEvent;
use AIArmada\Jnt\Features\Tracking\Models\JntWebhookLog;

final readonly class OrderTrackingCleanup implements DeleteOrderTracking
{
    public function handle(string $orderId, ?string $ownerType, ?string $ownerId): void
    {
        JntTrackingEvent::query()->withoutOwnerScope()
            ->where('order_id', $orderId)->where('owner_type', $ownerType)->where('owner_id', $ownerId)->delete();

        JntWebhookLog::query()->withoutOwnerScope()
            ->where('order_id', $orderId)->where('owner_type', $ownerType)->where('owner_id', $ownerId)->update(['order_id' => null]);
    }
}
