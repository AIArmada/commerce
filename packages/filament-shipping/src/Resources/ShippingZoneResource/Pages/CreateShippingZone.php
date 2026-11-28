<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingZoneResource\Pages;

use AIArmada\FilamentShipping\Resources\ShippingZoneResource;
use AIArmada\Shipping\Features\Rates\Api\ManageRateConfiguration;
use AIArmada\Shipping\Features\Rates\Models\ShippingZone;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateShippingZone extends CreateRecord
{
    protected static string $resource = ShippingZoneResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(ManageRateConfiguration::class)->createZone($data);

        return ShippingZone::query()->findOrFail($id);
    }
}
