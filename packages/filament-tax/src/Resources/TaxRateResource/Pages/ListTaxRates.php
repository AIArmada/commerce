<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxRateResource\Pages;

use AIArmada\FilamentTax\Resources\TaxRateResource;
use Filament\Resources\Pages\ListRecords;

class ListTaxRates extends ListRecords
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
