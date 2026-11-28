<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTaxonomyResource\Pages;

use AIArmada\Events\Features\Taxonomy\Actions\ManageEventTaxonomyAction;
use AIArmada\FilamentEvents\Resources\EventTaxonomyResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditEventTaxonomy extends EditRecord
{
    protected static string $resource = EventTaxonomyResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ManageEventTaxonomyAction::class)->save((string) $record->getKey(), $data, Filament::auth()->user());
    }
}
