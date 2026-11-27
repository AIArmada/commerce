<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Persistence;

use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Tracking\Api\DeleteOrderTracking;
use Illuminate\Support\Facades\DB;

final readonly class DeleteOrderChildren
{
    public function __construct(private DeleteOrderTracking $tracking) {}

    public function handle(JntOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->items()->delete();
            $order->parcels()->delete();
            $this->tracking->handle((string) $order->getKey(), $order->owner_type, $order->owner_id);
        });
    }
}
