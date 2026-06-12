<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources\ResponseResource\Pages;

use AIArmada\FilamentEngagement\Resources\ResponseResource;
use Filament\Resources\Pages\ListRecords;

final class ListResponses extends ListRecords
{
    protected static string $resource = ResponseResource::class;
}
