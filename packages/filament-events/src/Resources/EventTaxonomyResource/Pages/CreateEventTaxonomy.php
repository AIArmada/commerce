<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTaxonomyResource\Pages;

use AIArmada\Events\Features\Taxonomy\Actions\ManageEventTaxonomyAction;
use AIArmada\FilamentEvents\Resources\EventTaxonomyResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateEventTaxonomy extends CreateRecord
{
    protected static string $resource = EventTaxonomyResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ManageEventTaxonomyAction::class)->save(null, $data, Filament::auth()->user());
    }
}
