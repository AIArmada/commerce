<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources\BookmarkCollectionResource\Pages;

use AIArmada\Engagement\Actions\CreateBookmarkCollectionAction;
use AIArmada\Engagement\Models\BookmarkCollection;
use AIArmada\FilamentEngagement\Resources\BookmarkCollectionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateBookmarkCollection extends CreateRecord
{
    protected static string $resource = BookmarkCollectionResource::class;

    protected function handleRecordCreation(array $data): BookmarkCollection
    {
        return app(CreateBookmarkCollectionAction::class)->execute($data);
    }
}
