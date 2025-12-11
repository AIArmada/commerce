<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\Pages;

use AIArmada\FilamentTax\Resources\TaxZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxZone extends CreateRecord
{
    protected static string $resource = TaxZoneResource::class;
}
