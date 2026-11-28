<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages;

use AIArmada\Events\Features\Venues\Actions\ManageVenueSpaceAction;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVenueSpace extends CreateRecord
{
    protected static string $resource = VenueSpaceResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ManageVenueSpaceAction::class)->save(null, $data, Filament::auth()->user());
    }
}
