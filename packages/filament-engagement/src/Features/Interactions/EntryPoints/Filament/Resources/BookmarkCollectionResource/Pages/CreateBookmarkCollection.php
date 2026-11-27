<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkCollectionResource\Pages;

use AIArmada\Engagement\Features\Interactions\Actions\CreateBookmarkCollectionAction;
use AIArmada\Engagement\Features\Interactions\Models\BookmarkCollection;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkCollectionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateBookmarkCollection extends CreateRecord
{
    protected static string $resource = BookmarkCollectionResource::class;

    protected function handleRecordCreation(array $data): BookmarkCollection
    {
        return app(CreateBookmarkCollectionAction::class)->execute($data);
    }
}
