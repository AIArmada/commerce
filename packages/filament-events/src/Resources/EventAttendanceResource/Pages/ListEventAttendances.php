<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventAttendanceResource\Pages;

use AIArmada\FilamentEvents\Resources\EventAttendanceResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventAttendances extends ListRecords
{
    protected static string $resource = EventAttendanceResource::class;
}
