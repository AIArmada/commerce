<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLocationResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use AIArmada\Inventory\Features\Stock\Actions\SaveInventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditInventoryLocation extends EditRecord
{
    protected static string $resource = InventoryLocationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): InventoryLocation
    {
        abort_unless($record instanceof InventoryLocation, 404);

        return app(SaveInventoryLocation::class)->update($record, $data);
    }
}
