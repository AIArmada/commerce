<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Queries;

use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Events\InventoryReleaseRequired;
use AIArmada\Orders\Models\Order;

final readonly class OrderInventoryIdentity
{
    public static function find(InventoryDeductionRequired | InventoryReleaseRequired $event, bool $lock = false): ?Order
    {
        $query = Order::query()
            ->whereKey($event->order->getKey())
            ->where('owner_type', $event->owner_type)
            ->where('owner_id', $event->owner_id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
