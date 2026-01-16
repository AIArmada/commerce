<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Pages;

use AIArmada\FilamentPricing\Resources\PriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListPriceLists extends ListRecords
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
