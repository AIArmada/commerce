<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Pages;

use AIArmada\FilamentPricing\Resources\PriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditPriceList extends EditRecord
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
