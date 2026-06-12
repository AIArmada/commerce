<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Exports;

use AIArmada\Addressing\Models\AddressArea;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AddressAreaExporter extends Exporter
{
    protected static ?string $model = AddressArea::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('country_code')
                ->label('Country Code'),
            ExportColumn::make('type')
                ->label('Type'),
            ExportColumn::make('level')
                ->label('Level'),
            ExportColumn::make('name')
                ->label('Name'),
            ExportColumn::make('native_name')
                ->label('Native Name'),
            ExportColumn::make('code')
                ->label('Code'),
            ExportColumn::make('parent_source_id')
                ->label('Parent Source ID'),
            ExportColumn::make('source')
                ->label('Source'),
            ExportColumn::make('source_id')
                ->label('Source ID'),
            ExportColumn::make('latitude')
                ->label('Latitude'),
            ExportColumn::make('longitude')
                ->label('Longitude'),
            ExportColumn::make('synced_at')
                ->label('Synced At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your area export has completed and '
            . number_format($export->successful_rows) . ' '
            . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
