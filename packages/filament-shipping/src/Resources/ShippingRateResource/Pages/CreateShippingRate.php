<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingRateResource\Pages;

use AIArmada\FilamentShipping\Resources\ShippingRateResource;
use AIArmada\Shipping\Features\Rates\Api\ManageRateConfiguration;
use AIArmada\Shipping\Features\Rates\Models\ShippingRate;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateShippingRate extends CreateRecord
{
    protected static string $resource = ShippingRateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(ManageRateConfiguration::class)->createRate((string) $data['zone_id'], $data);

        return ShippingRate::query()->findOrFail($id);
    }
}
