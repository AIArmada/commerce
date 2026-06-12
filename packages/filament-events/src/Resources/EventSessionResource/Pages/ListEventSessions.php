<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSessionResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSessionResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventSessions extends ListRecords
{
    protected static string $resource = EventSessionResource::class;
}
