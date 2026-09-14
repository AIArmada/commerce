<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Exports;

use AIArmada\Addressing\Models\PostalCode;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PostalCodeExporter extends Exporter
{
    public static function getModel(): string
    {
        return config('filament-addressing.resources.postal_codes.model', PostalCode::class);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('country_code')
                ->label('Country Code'),
            ExportColumn::make('code')
                ->label('Postcode'),
            ExportColumn::make('is_active')
                ->label('Active'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your postcode export has completed and '
            . number_format($export->successful_rows) . ' '
            . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
