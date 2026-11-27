<?php

declare(strict_types=1);

namespace AIArmada\Inventory\EntryPoints\Listeners;

use AIArmada\Inventory\Actions\ReleaseOrderInventory;
use AIArmada\Orders\Events\InventoryReleaseRequired;

final readonly class ReleaseInventoryFromOrder
{
    public function __construct(private ReleaseOrderInventory $action) {}

    public function handle(InventoryReleaseRequired $event): void
    {
        $this->action->execute($event);
    }
}
