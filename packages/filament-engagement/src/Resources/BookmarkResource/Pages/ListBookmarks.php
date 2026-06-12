<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources\BookmarkResource\Pages;

use AIArmada\FilamentEngagement\Resources\BookmarkResource;
use Filament\Resources\Pages\ListRecords;

final class ListBookmarks extends ListRecords
{
    protected static string $resource = BookmarkResource::class;
}
