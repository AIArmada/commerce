<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Pages;

use AIArmada\FilamentPricing\Resources\PriceListResource;
use AIArmada\Pricing\Actions\UpdatePriceList;
use AIArmada\Pricing\Models\PriceList;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditPriceList extends EditRecord
{
    protected static string $resource = PriceListResource::class;

    protected function handleRecordUpdate(Model $record, array $data): PriceList
    {
        abort_unless($record instanceof PriceList, 404);

        return app(UpdatePriceList::class)->execute($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
