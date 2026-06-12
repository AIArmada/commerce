<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueResource\Pages;

use AIArmada\FilamentEvents\Resources\VenueResource;
use Filament\Resources\Pages\ListRecords;

final class ListVenues extends ListRecords
{
    protected static string $resource = VenueResource::class;
}
