<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Exports;

use AIArmada\Addressing\Models\AddressCountry;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AddressCountryExporter extends Exporter
{
    protected static ?string $model = AddressCountry::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('iso2')
                ->label('ISO2'),
            ExportColumn::make('iso3')
                ->label('ISO3'),
            ExportColumn::make('numeric_code')
                ->label('Numeric Code'),
            ExportColumn::make('name')
                ->label('Name'),
            ExportColumn::make('official_name')
                ->label('Official Name'),
            ExportColumn::make('native_name')
                ->label('Native Name'),
            ExportColumn::make('entity_type')
                ->label('Entity Type'),
            ExportColumn::make('is_independent')
                ->label('Independent'),
            ExportColumn::make('phone_code')
                ->label('Phone Code'),
            ExportColumn::make('default_currency_code')
                ->label('Currency'),
            ExportColumn::make('region')
                ->label('Region'),
            ExportColumn::make('subregion')
                ->label('Subregion'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your country export has completed and '
            . number_format($export->successful_rows) . ' '
            . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
