<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

final readonly class DispatchStockThresholdEvents
{
    public function execute(Model $model, InventoryLevel $level): void
    {
        if (! config('inventory.events.low_inventory', true)) {
            return;
        }

        $level->refresh();

        if ($level->available === 0 && config('inventory.events.out_of_inventory', true)) {
            Event::dispatch(new OutOfInventory($model, $level));
        } elseif ($level->isLowStock()) {
            Event::dispatch(new LowInventoryDetected($model, $level));
        }
    }

}
