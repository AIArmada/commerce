<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxClassResource\Pages;

use AIArmada\FilamentTax\Resources\TaxClassResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxClass extends CreateRecord
{
    protected static string $resource = TaxClassResource::class;
}
