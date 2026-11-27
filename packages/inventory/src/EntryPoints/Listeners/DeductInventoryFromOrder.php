<?php

declare(strict_types=1);

namespace AIArmada\Inventory\EntryPoints\Listeners;

use AIArmada\Inventory\Actions\DeductOrderInventory;
use AIArmada\Orders\Events\InventoryDeductionRequired;

final readonly class DeductInventoryFromOrder
{
    public function __construct(private DeductOrderInventory $action) {}

    public function handle(InventoryDeductionRequired $event): void
    {
        $this->action->execute($event);
    }
}
