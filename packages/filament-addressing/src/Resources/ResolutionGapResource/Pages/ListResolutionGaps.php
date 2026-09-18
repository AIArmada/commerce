<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\ResolutionGapResource\Pages;

use AIArmada\FilamentAddressing\Resources\ResolutionGapResource;
use Filament\Resources\Pages\ListRecords;

final class ListResolutionGaps extends ListRecords
{
    protected static string $resource = ResolutionGapResource::class;
}
