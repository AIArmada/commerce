<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\Pages;

use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewEventOccurrence extends ViewRecord
{
    protected static string $resource = EventOccurrenceResource::class;
}
