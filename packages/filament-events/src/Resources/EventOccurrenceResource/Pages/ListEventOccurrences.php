<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\Pages;

use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventOccurrences extends ListRecords
{
    protected static string $resource = EventOccurrenceResource::class;
}
