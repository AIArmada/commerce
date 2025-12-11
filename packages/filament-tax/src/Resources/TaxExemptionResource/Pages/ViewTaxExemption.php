<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Pages;

use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTaxExemption extends ViewRecord
{
    protected static string $resource = TaxExemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
