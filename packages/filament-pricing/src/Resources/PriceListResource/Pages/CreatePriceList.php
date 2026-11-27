<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Pages;

use AIArmada\FilamentPricing\Resources\PriceListResource;
use AIArmada\Pricing\Actions\CreatePriceList as CreatePriceListAction;
use AIArmada\Pricing\Models\PriceList;
use Filament\Resources\Pages\CreateRecord;

final class CreatePriceList extends CreateRecord
{
    protected static string $resource = PriceListResource::class;

    protected function handleRecordCreation(array $data): PriceList
    {
        return app(CreatePriceListAction::class)->execute($data);
    }
}
