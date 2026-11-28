<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTermResource\Pages;

use AIArmada\Events\Features\Taxonomy\Actions\ManageEventTermAction;
use AIArmada\FilamentEvents\Resources\EventTermResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateEventTerm extends CreateRecord
{
    protected static string $resource = EventTermResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ManageEventTermAction::class)->save((string) $data['event_taxonomy_id'], null, $data, Filament::auth()->user());
    }
}
