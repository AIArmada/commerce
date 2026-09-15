<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\RelationManagers;

use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * Embed the event table on any owner with an `events` relationship.
 *
 * Register it from the host resource's `getRelations()`; the table renders
 * from {@see EventResource} through `$relatedResource`, and create/edit
 * actions link to the event resource pages.
 */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Events';

    protected static ?string $relatedResource = EventResource::class;

    public function table(Table $table): Table
    {
        return $table;
    }
}
