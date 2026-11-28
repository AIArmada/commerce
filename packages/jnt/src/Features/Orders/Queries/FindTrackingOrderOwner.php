<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Features\Orders\Api\ReadTrackingOrderOwner;
use AIArmada\Jnt\Features\Orders\Api\TrackingOrderOwner;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;

final readonly class FindTrackingOrderOwner implements ReadTrackingOrderOwner
{
    public function findForEvent(string $orderId): ?TrackingOrderOwner
    {
        return $this->snapshot(JntOrder::query()->withoutOwnerScope()->find($orderId));
    }

    public function findForLog(string $orderId): ?TrackingOrderOwner
    {
        $order = (bool) config('jnt.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(
                modelClass: JntOrder::class,
                id: $orderId,
                owner: OwnerContext::CURRENT,
                includeGlobal: (bool) config('jnt.owner.include_global', false),
            )
            : JntOrder::query()->find($orderId);

        return $this->snapshot($order);
    }

    private function snapshot(?JntOrder $order): ?TrackingOrderOwner
    {
        return $order === null ? null : new TrackingOrderOwner(
            $order->owner_type,
            $order->owner_id === null ? null : (string) $order->owner_id,
        );
    }
}
