<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Pages;

use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use Filament\Resources\Pages\ListRecords;

class ListTaxExemptions extends ListRecords
{
    protected static string $resource = TaxExemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
