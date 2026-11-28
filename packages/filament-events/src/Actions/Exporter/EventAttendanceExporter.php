<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Platform\ModelResolver;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class EventAttendanceExporter extends Exporter
{
    public static function getModel(): string
    {
        return ModelResolver::attendanceClass();
    }

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

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your event attendance export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
