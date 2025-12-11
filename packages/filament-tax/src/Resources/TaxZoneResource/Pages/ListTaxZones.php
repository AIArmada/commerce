<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\Pages;

use AIArmada\FilamentTax\Resources\TaxZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxZones extends ListRecords
{
    protected static string $resource = TaxZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
