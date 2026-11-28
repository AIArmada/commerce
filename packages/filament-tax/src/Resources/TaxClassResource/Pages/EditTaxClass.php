<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxClassResource\Pages;

use AIArmada\FilamentTax\Resources\TaxClassResource;
use AIArmada\Tax\Actions\DeleteTaxClassesAction;
use AIArmada\Tax\Actions\SaveTaxClassAction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTaxClass extends EditRecord
{
    protected static string $resource = TaxClassResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SaveTaxClassAction::class)->execute($data, $record->getKey());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->authorize(fn (): bool => auth()->user()?->can('tax.classes.delete') ?? false)
                ->using(function (Model $record): bool {
                    app(DeleteTaxClassesAction::class)->execute([$record->getKey()]);

                    return true;
                }),
        ];
    }
}
