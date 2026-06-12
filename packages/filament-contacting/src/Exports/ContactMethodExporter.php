<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Exports;

use AIArmada\Contacting\Models\ContactMethod;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

final class ContactMethodExporter extends Exporter
{
    protected static ?string $model = ContactMethod::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('contactable_type'),
            ExportColumn::make('contactable_id'),
            ExportColumn::make('type'),
            ExportColumn::make('label'),
            ExportColumn::make('value'),
            ExportColumn::make('normalized_value'),
            ExportColumn::make('country_code'),
            ExportColumn::make('is_primary'),
            ExportColumn::make('is_public'),
            ExportColumn::make('is_verified'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }
}
