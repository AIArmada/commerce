<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkCollectionResource\Pages;

use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkCollectionResource;
use Filament\Resources\Pages\ListRecords;

final class ListBookmarkCollections extends ListRecords
{
    protected static string $resource = BookmarkCollectionResource::class;
}
