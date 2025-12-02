<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLocationResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use Filament\Resources\Pages\ListRecords;

final class ListInventoryLocations extends ListRecords
{
    protected static string $resource = InventoryLocationResource::class;
}
