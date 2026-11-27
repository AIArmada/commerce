<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShipmentResource\Pages;

use AIArmada\FilamentShipping\Resources\ShipmentResource;
use Filament\Actions;
use AIArmada\Shipping\Features\Shipments\Api\ManageManualShipment;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;

class EditShipment extends EditRecord
{
    protected static string $resource = ShipmentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(ManageManualShipment::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->using(fn (Shipment $record): bool => app(ManageManualShipment::class)->delete((string) $record->getKey())),
        ];
    }
}
