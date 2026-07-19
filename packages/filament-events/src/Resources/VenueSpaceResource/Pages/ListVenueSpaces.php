<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages;

use AIArmada\FilamentEvents\Resources\VenueSpaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVenueSpaces extends ListRecords
{
    protected static string $resource = VenueSpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
