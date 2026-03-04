<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingRateResource\Pages;

use AIArmada\FilamentShipping\Resources\ShippingRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingRate extends EditRecord
{
    protected static string $resource = ShippingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
