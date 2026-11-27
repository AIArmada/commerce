<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Tracking\Api\SyncStoredTracking;
use DomainException;

final class SyncStoredOrderTracking implements SyncStoredTracking
{
    public function execute(string $orderId): void
    {
        $order = (bool) config('jnt.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $orderId)
            : JntOrder::query()->findOrFail($orderId);

        if ($order->tracking_number === null || $order->tracking_number === '') {
            throw new DomainException('The order has no tracking number.');
        }

        app(JntTrackingService::class)->syncOrderTracking($order);
    }
}
