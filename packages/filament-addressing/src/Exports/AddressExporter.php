<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Exports;

use AIArmada\Addressing\Models\Address;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class AddressExporter extends Exporter
{
    public static function getModel(): string
    {
        return config('filament-addressing.resources.addresses.model', Address::class);
    }

    /**
     * @param  Builder<Address>  $query
     * @return Builder<Address>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return OwnerUiScope::apply($query, includeGlobal: false);
    }

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
