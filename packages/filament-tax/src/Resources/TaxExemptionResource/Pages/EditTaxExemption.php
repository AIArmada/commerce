<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Pages;

use AIArmada\FilamentTax\Actions\ValidateExemptableEntityAction;
use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use AIArmada\Tax\Actions\Exemption\DeleteTaxExemptionAction;
use AIArmada\Tax\Actions\Exemption\UpdateTaxExemptionAction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTaxExemption extends EditRecord
{
    protected static string $resource = TaxExemptionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(ValidateExemptableEntityAction::class)->execute($data);

        return app(UpdateTaxExemptionAction::class)->execute($record->getKey(), $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.exemptions.view') ?? false),
            Actions\DeleteAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.exemptions.delete') ?? false)
                ->using(fn (Model $record) => app(DeleteTaxExemptionAction::class)->execute($record->getKey())),
        ];
    }
}
