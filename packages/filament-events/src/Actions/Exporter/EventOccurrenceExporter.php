<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Models\EventOccurrence;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

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
}
