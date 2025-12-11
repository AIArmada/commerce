<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxRateResource\Pages;

use AIArmada\FilamentTax\Resources\TaxRateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxRate extends CreateRecord
{
    protected static string $resource = TaxRateResource::class;
}
