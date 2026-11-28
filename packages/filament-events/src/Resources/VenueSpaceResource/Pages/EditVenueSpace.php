<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages;

use AIArmada\Events\Features\Venues\Actions\ManageVenueSpaceAction;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource;
use Filament\Facades\Filament;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVenueSpace extends EditRecord
{
    protected static string $resource = VenueSpaceResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ManageVenueSpaceAction::class)->save((string) $record->getKey(), $data, Filament::auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(fn (Model $record): bool => app(ManageVenueSpaceAction::class)->delete((string) $record->getKey(), Filament::auth()->user())),
        ];
    }
}
