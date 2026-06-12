<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Models\EventAttendance;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

final class EventAttendanceExporter extends Exporter
{
    protected static ?string $model = EventAttendance::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('event.title')->label('Event'),
            ExportColumn::make('occurrence.title')->label('Occurrence'),
            ExportColumn::make('attendance_type'),
            ExportColumn::make('checked_in_at'),
            ExportColumn::make('checked_out_at'),
            ExportColumn::make('check_in_source'),
            ExportColumn::make('attendee_type'),
            ExportColumn::make('attendee_id'),
            ExportColumn::make('created_at'),
        ];
    }
}
