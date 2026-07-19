<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages;

use AIArmada\FilamentEvents\Resources\VenueSpaceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVenueSpace extends ViewRecord
{
    protected static string $resource = VenueSpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
