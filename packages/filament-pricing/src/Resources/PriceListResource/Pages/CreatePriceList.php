<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Pages;

use AIArmada\FilamentPricing\Resources\PriceListResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePriceList extends CreateRecord
{
    protected static string $resource = PriceListResource::class;
}
