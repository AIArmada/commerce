<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Pages;

use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxExemption extends CreateRecord
{
    protected static string $resource = TaxExemptionResource::class;
}
