<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Models\EventRegistration;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class EventRegistrationExporter extends Exporter
{
    protected static ?string $model = EventRegistration::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('registration_no'),
            ExportColumn::make('event.title')->label('Event'),
            ExportColumn::make('occurrence.title')->label('Occurrence'),
            ExportColumn::make('registration_type'),
            ExportColumn::make('status'),
            ExportColumn::make('source'),
            ExportColumn::make('total_participants'),
            ExportColumn::make('total_amount'),
            ExportColumn::make('currency'),
            ExportColumn::make('payment_status'),
            ExportColumn::make('registered_at'),
            ExportColumn::make('approved_at'),
            ExportColumn::make('cancelled_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your event registration export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
