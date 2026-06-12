<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Models\EventTicketType;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\ExportColumn;

final class EventTicketTypeExporter extends Exporter
{
    protected static ?string $model = EventTicketType::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('event.title')->label('Event'),
            ExportColumn::make('name'),
            ExportColumn::make('code'),
            ExportColumn::make('access_type'),
            ExportColumn::make('price'),
            ExportColumn::make('currency'),
            ExportColumn::make('quota'),
            ExportColumn::make('status'),
            ExportColumn::make('admits_quantity'),
            ExportColumn::make('sales_starts_at'),
            ExportColumn::make('sales_ends_at'),
        ];
    }
}
