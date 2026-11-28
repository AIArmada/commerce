<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Queries;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Features\Orders\Api\ReadWaybillOrder;
use AIArmada\Jnt\Features\Orders\Api\WaybillOrder;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;

final readonly class FindWaybillOrder implements ReadWaybillOrder
{
    public function find(string $id): WaybillOrder
    {
        $order = (bool) config('jnt.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $id)
            : JntOrder::query()->findOrFail($id);

        return new WaybillOrder($order->order_id, $order->tracking_number);
    }
}
