<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxClassResource\Pages;

use AIArmada\FilamentTax\Resources\TaxClassResource;
use AIArmada\Tax\Actions\SaveTaxClassAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTaxClass extends CreateRecord
{
    protected static string $resource = TaxClassResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveTaxClassAction::class)->execute($data);
    }
}
