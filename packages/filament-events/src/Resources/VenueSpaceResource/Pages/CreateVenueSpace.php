<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages;

use AIArmada\FilamentEvents\Resources\VenueSpaceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVenueSpace extends CreateRecord
{
    protected static string $resource = VenueSpaceResource::class;
}
