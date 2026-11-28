<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Pages;

use AIArmada\FilamentTax\Actions\ValidateExemptableEntityAction;
use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use AIArmada\Tax\Actions\Exemption\RequestTaxExemption;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTaxExemption extends CreateRecord
{
    protected static string $resource = TaxExemptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        app(ValidateExemptableEntityAction::class)->execute($data);

        return app(RequestTaxExemption::class)->execute($data);
    }
}
