<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxRateResource\Pages;

use AIArmada\FilamentTax\Resources\TaxRateResource;
use AIArmada\Tax\Actions\DeleteTaxRatesAction;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewTaxRate extends ViewRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.rates.update') ?? false),
            Actions\DeleteAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.rates.delete') ?? false)
                ->using(function (Model $record): bool {
                    app(DeleteTaxRatesAction::class)->execute([$record->getKey()]);

                    return true;
                }),
        ];
    }
}
