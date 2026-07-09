<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Models\Event;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class EventExporter extends Exporter
{
    protected static ?string $model = Event::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('title'),
            ExportColumn::make('slug'),
            ExportColumn::make('type'),
            ExportColumn::make('status'),
            ExportColumn::make('visibility'),
            ExportColumn::make('delivery_mode'),
            ExportColumn::make('timezone'),
            ExportColumn::make('summary'),
            ExportColumn::make('published_at'),
            ExportColumn::make('cancelled_at'),
            ExportColumn::make('completed_at'),
            ExportColumn::make('last_state_change_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your event export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
