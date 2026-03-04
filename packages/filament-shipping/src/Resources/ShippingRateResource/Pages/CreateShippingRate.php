<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingRateResource\Pages;

use AIArmada\FilamentShipping\Resources\ShippingRateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingRate extends CreateRecord
{
    protected static string $resource = ShippingRateResource::class;
}
