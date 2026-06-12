<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources\BookmarkCollectionResource\Pages;

use AIArmada\FilamentEngagement\Resources\BookmarkCollectionResource;
use Filament\Resources\Pages\ListRecords;

final class ListBookmarkCollections extends ListRecords
{
    protected static string $resource = BookmarkCollectionResource::class;
}
