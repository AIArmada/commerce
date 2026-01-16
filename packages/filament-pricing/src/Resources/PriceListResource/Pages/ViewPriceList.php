<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Pages;

use AIArmada\FilamentPricing\Resources\PriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewPriceList extends ViewRecord
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
