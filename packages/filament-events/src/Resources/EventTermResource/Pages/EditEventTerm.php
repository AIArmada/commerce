<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTermResource\Pages;

use AIArmada\Events\Features\Taxonomy\Actions\ManageEventTermAction;
use AIArmada\FilamentEvents\Resources\EventTermResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditEventTerm extends EditRecord
{
    protected static string $resource = EventTermResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ManageEventTermAction::class)->save((string) $record->getAttribute('event_taxonomy_id'), (string) $record->getKey(), $data, Filament::auth()->user());
    }
}
