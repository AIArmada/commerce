<?php

declare(strict_types=1);

namespace AIArmada\Inventory\EntryPoints\Listeners;

use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Inventory\Actions\ValidateCartInventory;

final readonly class ValidateInventoryOnAdd
{
    public function __construct(private ValidateCartInventory $action) {}

    public function handle(ItemAdded $event): void
    {
        $this->action->execute($event);
    }
}
