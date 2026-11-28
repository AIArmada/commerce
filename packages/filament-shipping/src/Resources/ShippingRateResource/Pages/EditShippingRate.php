<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingRateResource\Pages;

use AIArmada\FilamentShipping\Resources\ShippingRateResource;
use AIArmada\Shipping\Features\Rates\Api\ManageRateConfiguration;
use AIArmada\Shipping\Features\Rates\Models\ShippingRate;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditShippingRate extends EditRecord
{
    protected static string $resource = ShippingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn (ShippingRate $record): bool => app(ManageRateConfiguration::class)->deleteRate((string) $record->getKey())),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(ManageRateConfiguration::class)->updateRate((string) $record->getKey(), $data);

        return $record->refresh();
    }
}
