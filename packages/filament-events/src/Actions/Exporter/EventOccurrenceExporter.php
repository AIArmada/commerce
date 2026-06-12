<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Models\EventOccurrence;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class EventOccurrenceExporter extends Exporter
{
    protected static ?string $model = EventOccurrence::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('event.title')->label('Event'),
            ExportColumn::make('title'),
            ExportColumn::make('starts_at'),
            ExportColumn::make('ends_at'),
            ExportColumn::make('timezone'),
            ExportColumn::make('status'),
            ExportColumn::make('visibility'),
            ExportColumn::make('capacity'),
            ExportColumn::make('published_at'),
            ExportColumn::make('cancelled_at'),
            ExportColumn::make('completed_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your event occurrence export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
