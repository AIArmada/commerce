<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources\ReactionResource\Pages;

use AIArmada\FilamentEngagement\Resources\ReactionResource;
use Filament\Resources\Pages\ListRecords;

final class ListReactions extends ListRecords
{
    protected static string $resource = ReactionResource::class;
}
