<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTermResource\Pages;

use AIArmada\FilamentEvents\Resources\EventTermResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventTerms extends ListRecords
{
    protected static string $resource = EventTermResource::class;
}
