<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventAttendanceResource\Pages;

use AIArmada\FilamentEvents\Resources\EventAttendanceResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewEventAttendance extends ViewRecord
{
    protected static string $resource = EventAttendanceResource::class;
}
