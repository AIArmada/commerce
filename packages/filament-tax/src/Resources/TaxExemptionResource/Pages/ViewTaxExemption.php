<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Pages;

use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use AIArmada\Tax\Actions\Exemption\DeleteTaxExemptionsAction;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewTaxExemption extends ViewRecord
{
    protected static string $resource = TaxExemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.exemptions.update') ?? false),
            Actions\DeleteAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.exemptions.delete') ?? false)
                ->using(function (Model $record): void {
                    app(DeleteTaxExemptionsAction::class)->execute([$record->getKey()]);
                }),
        ];
    }
}
