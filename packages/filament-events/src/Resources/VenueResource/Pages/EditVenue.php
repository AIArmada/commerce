<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueResource\Pages;

use AIArmada\FilamentEvents\Resources\VenueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
