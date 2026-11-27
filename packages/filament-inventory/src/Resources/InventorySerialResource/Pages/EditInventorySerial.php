<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages;

use AIArmada\FilamentInventory\Resources\InventorySerialResource;
use AIArmada\Inventory\Features\Traceability\Actions\UpdateTraceabilityDetails;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use AIArmada\FilamentInventory\Actions\DeleteTraceabilityAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditInventorySerial extends EditRecord
{
    protected static string $resource = InventorySerialResource::class;

    protected function handleRecordUpdate(Model $record, array $data): InventorySerial
    {
        abort_unless($record instanceof InventorySerial, 404);

        return app(UpdateTraceabilityDetails::class)->serial($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteTraceabilityAction::make(),
        ];
    }
}
