<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\ResponseResource\Pages;

use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\ResponseResource;
use Filament\Resources\Pages\ListRecords;

final class ListResponses extends ListRecords
{
    protected static string $resource = ResponseResource::class;
}
