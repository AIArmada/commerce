<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages;

use AIArmada\FilamentInventory\Resources\InventorySerialResource;
use AIArmada\Inventory\Features\Traceability\Actions\RegisterTraceability;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventorySerial extends CreateRecord
{
    protected static string $resource = InventorySerialResource::class;

    protected function handleRecordCreation(array $data): InventorySerial
    {
        return app(RegisterTraceability::class)->serial((string) $data['inventory_level_id'], $data);
    }
}
