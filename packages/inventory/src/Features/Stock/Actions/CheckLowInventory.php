<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Check and dispatch low inventory events.
 */
final class CheckLowInventory
{
    use AsAction;

    /**
     * Check if inventory is below reorder point and dispatch event.
     */
    public function handle(Model $model, InventoryLevel $level): bool
    {
        if (! config('inventory.events.low_inventory', true)) {
            return false;
        }

        $level->refresh();

        if ($level->available === 0 && config('inventory.events.out_of_inventory', true)) {
            Event::dispatch(new OutOfInventory($model, $level));

            return true;
        }

        if ($level->isLowStock()) {
            Event::dispatch(new LowInventoryDetected($model, $level));

            return true;
        }

        return false;
    }
}
