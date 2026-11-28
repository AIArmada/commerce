<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\Pages;

use AIArmada\FilamentTax\Resources\TaxZoneResource;
use AIArmada\Tax\Actions\DeleteTaxZonesAction;
use AIArmada\Tax\Actions\SaveTaxZoneAction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTaxZone extends EditRecord
{
    protected static string $resource = TaxZoneResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SaveTaxZoneAction::class)->execute($data, $record->getKey());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.zones.view') ?? false),
            Actions\DeleteAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.zones.delete') ?? false)
                ->using(function (Model $record): bool {
                    app(DeleteTaxZonesAction::class)->execute([$record->getKey()]);

                    return true;
                }),
        ];
    }
}
