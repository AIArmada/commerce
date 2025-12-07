<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingZoneResource\Pages;

use AIArmada\FilamentShipping\Resources\ShippingZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingZone extends CreateRecord
{
    protected static string $resource = ShippingZoneResource::class;
}
