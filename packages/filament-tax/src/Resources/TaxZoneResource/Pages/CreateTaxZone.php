<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\Pages;

use AIArmada\FilamentTax\Resources\TaxZoneResource;
use AIArmada\Tax\Actions\SaveTaxZoneAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTaxZone extends CreateRecord
{
    protected static string $resource = TaxZoneResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveTaxZoneAction::class)->execute($data);
    }
}
