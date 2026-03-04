<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingRateResource\Pages;

use AIArmada\FilamentShipping\Resources\ShippingRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShippingRates extends ListRecords
{
    protected static string $resource = ShippingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
