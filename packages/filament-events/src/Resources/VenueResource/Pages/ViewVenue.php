<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueResource\Pages;

use AIArmada\FilamentEvents\Resources\VenueResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewVenue extends ViewRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
