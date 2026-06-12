<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Events\Models\Venue;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\ExportColumn;

final class VenueExporter extends Exporter
{
    protected static ?string $model = Venue::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('venue_type'),
            ExportColumn::make('address_line_1'),
            ExportColumn::make('city'),
            ExportColumn::make('state'),
            ExportColumn::make('country'),
            ExportColumn::make('status'),
            ExportColumn::make('phone'),
            ExportColumn::make('email'),
            ExportColumn::make('created_at'),
        ];
    }
}
