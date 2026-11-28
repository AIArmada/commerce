<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use AIArmada\Inventory\Features\Traceability\Actions\RegisterTraceability;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventoryBatch extends CreateRecord
{
    protected static string $resource = InventoryBatchResource::class;

    protected function handleRecordCreation(array $data): InventoryBatch
    {
        return app(RegisterTraceability::class)->batch((string) $data['inventory_level_id'], $data);
    }
}
