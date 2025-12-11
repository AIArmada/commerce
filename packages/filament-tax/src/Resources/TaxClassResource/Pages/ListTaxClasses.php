<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxClassResource\Pages;

use AIArmada\FilamentTax\Resources\TaxClassResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxClasses extends ListRecords
{
    protected static string $resource = TaxClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
