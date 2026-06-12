<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources\BookmarkCollectionResource\Pages;

use AIArmada\FilamentEngagement\Resources\BookmarkCollectionResource;
use Filament\Resources\Pages\EditRecord;

final class EditBookmarkCollection extends EditRecord
{
    protected static string $resource = BookmarkCollectionResource::class;
}
