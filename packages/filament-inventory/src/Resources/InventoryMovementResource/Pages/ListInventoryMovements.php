<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryMovementResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryMovementResource;
use Filament\Resources\Pages\ListRecords;

final class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;
}
