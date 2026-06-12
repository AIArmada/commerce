<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Models\EventSession;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\ExportColumn;

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
}
