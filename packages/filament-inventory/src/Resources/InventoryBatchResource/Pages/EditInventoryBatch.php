<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages;

use AIArmada\FilamentInventory\Actions\DeleteTraceabilityAction;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use AIArmada\Inventory\Features\Traceability\Actions\UpdateTraceabilityDetails;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditInventoryBatch extends EditRecord
{
    protected static string $resource = InventoryBatchResource::class;

    protected function handleRecordUpdate(Model $record, array $data): InventoryBatch
    {
        abort_unless($record instanceof InventoryBatch, 404);

        return app(UpdateTraceabilityDetails::class)->batch($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteTraceabilityAction::make(),
        ];
    }
}
