<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLocationResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use Filament\Resources\Pages\EditRecord;

final class EditInventoryLocation extends EditRecord
{
    protected static string $resource = InventoryLocationResource::class;
}
