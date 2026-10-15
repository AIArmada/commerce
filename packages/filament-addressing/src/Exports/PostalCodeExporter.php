<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Exports;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\PostalCode;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class PostalCodeExporter extends Exporter
{
    public static function getModel(): string
    {
        return config('filament-addressing.resources.postal_codes.model', PostalCode::class);
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('areas');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('country_code')
                ->label('Country Code'),
            ExportColumn::make('code')
                ->label('Postcode'),
            ExportColumn::make('is_active')
                ->label('Active')
                ->state(static fn (PostalCode $record): string => $record->is_active ? '1' : '0'),
            ExportColumn::make('metadata')
                ->label('Metadata')
                ->state(static function (PostalCode $record): string {
                    return json_encode($record->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }),
            ExportColumn::make('coverage')
                ->label('Coverage')
                ->state(static function (PostalCode $record): string {
                    $coverage = $record->areas->map(static fn (AddressArea $area): array => [
                        'source' => data_get($area, 'pivot.source'),
                        'source_id' => data_get($area, 'pivot.source_id'),
                        'area_source' => $area->source,
                        'area_source_id' => $area->source_id,
                        'relationship_type' => data_get($area, 'pivot.relationship_type'),
                        'is_primary' => (bool) data_get($area, 'pivot.is_primary'),
                    ])->sortBy(static fn (array $item): string => implode("\0", [
                        $item['source'],
                        $item['source_id'] ?? '',
                        $item['area_source'],
                        $item['area_source_id'],
                        $item['relationship_type'],
                    ]))->values()->all();

                    return json_encode($coverage, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }),
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
