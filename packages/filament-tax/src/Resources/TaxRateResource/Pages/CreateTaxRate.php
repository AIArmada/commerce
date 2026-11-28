<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxRateResource\Pages;

use AIArmada\FilamentTax\Resources\TaxRateResource;
use AIArmada\Tax\Actions\SaveTaxRateAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTaxRate extends CreateRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveTaxRateAction::class)->execute($data);
    }
}
