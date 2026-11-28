<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkCollectionResource\Pages;

use AIArmada\Engagement\Features\Interactions\Actions\UpdateBookmarkCollectionAction;
use AIArmada\Engagement\Features\Interactions\Models\BookmarkCollection;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkCollectionResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditBookmarkCollection extends EditRecord
{
    protected static string $resource = BookmarkCollectionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof BookmarkCollection, 404);

        return app(UpdateBookmarkCollectionAction::class)->execute($record, $data);
    }
}
