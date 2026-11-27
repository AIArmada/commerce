<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLocationResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use AIArmada\Inventory\Actions\SaveInventoryLocation;
use AIArmada\Inventory\Models\InventoryLocation;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventoryLocation extends CreateRecord
{
    protected static string $resource = InventoryLocationResource::class;

    protected function handleRecordCreation(array $data): InventoryLocation
    {
        return app(SaveInventoryLocation::class)->create($data);
    }
}
