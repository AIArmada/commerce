<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Models\EventSession;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class EventSessionExporter extends Exporter
{
    protected static ?string $model = EventSession::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('event.title')->label('Event'),
            ExportColumn::make('occurrence.title')->label('Occurrence'),
            ExportColumn::make('title'),
            ExportColumn::make('starts_at'),
            ExportColumn::make('ends_at'),
            ExportColumn::make('status'),
            ExportColumn::make('capacity'),
            ExportColumn::make('sort_order'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your event session export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
