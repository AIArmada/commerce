<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingZoneResource\Pages;

use AIArmada\FilamentShipping\Resources\ShippingZoneResource;
use AIArmada\Shipping\Features\Rates\Api\ManageRateConfiguration;
use AIArmada\Shipping\Features\Rates\Models\ShippingZone;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditShippingZone extends EditRecord
{
    protected static string $resource = ShippingZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make()
                ->using(fn (ShippingZone $record): bool => app(ManageRateConfiguration::class)->deleteZone((string) $record->getKey())),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(ManageRateConfiguration::class)->updateZone((string) $record->getKey(), $data);

        return $record->refresh();
    }
}
