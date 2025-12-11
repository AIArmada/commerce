<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxRateResource\Pages;

use AIArmada\FilamentTax\Resources\TaxRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTaxRate extends ViewRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
