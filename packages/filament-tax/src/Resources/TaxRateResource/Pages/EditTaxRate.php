<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxRateResource\Pages;

use AIArmada\FilamentTax\Resources\TaxRateResource;
use AIArmada\Tax\Actions\DeleteTaxRatesAction;
use AIArmada\Tax\Actions\SaveTaxRateAction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTaxRate extends EditRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SaveTaxRateAction::class)->execute($data, $record->getKey());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.rates.view') ?? false),
            Actions\DeleteAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.rates.delete') ?? false)
                ->using(function (Model $record): bool {
                    app(DeleteTaxRatesAction::class)->execute([$record->getKey()]);

                    return true;
                }),
        ];
    }
}
