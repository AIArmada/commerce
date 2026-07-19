<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages;

use AIArmada\FilamentEvents\Resources\VenueSpaceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVenueSpace extends EditRecord
{
    protected static string $resource = VenueSpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
