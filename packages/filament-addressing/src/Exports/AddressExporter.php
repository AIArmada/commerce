<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Exports;

use AIArmada\Addressing\Models\Address;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AddressExporter extends Exporter
{
    protected static ?string $model = Address::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('label')
                ->label('Label'),
            ExportColumn::make('line1')
                ->label('Line 1'),
            ExportColumn::make('line2')
                ->label('Line 2'),
            ExportColumn::make('city')
                ->label('City'),
            ExportColumn::make('state')
                ->label('State'),
            ExportColumn::make('postcode')
                ->label('Postcode'),
            ExportColumn::make('country_code')
                ->label('Country Code'),
            ExportColumn::make('country')
                ->label('Country'),
            ExportColumn::make('formatted_address')
                ->label('Formatted Address'),
            ExportColumn::make('validation_status')
                ->label('Validation Status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your address export has completed and '
            . number_format($export->successful_rows) . ' '
            . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
