<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\FollowResource\Pages;

use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\FollowResource;
use Filament\Resources\Pages\ListRecords;

final class ListFollows extends ListRecords
{
    protected static string $resource = FollowResource::class;
}
