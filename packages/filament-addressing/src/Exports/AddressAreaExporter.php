<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Exports;

use AIArmada\Addressing\Features\Geography\Models\AddressArea;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AddressAreaExporter extends Exporter
{
    public static function getModel(): string
    {
        return config('filament-addressing.resources.areas.model', AddressArea::class);
    }

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
            ExportColumn::make('relationship_type')
                ->state(static fn (AddressArea $record): ?string => self::ancestorPivotValue($record, 'relationship_type')),
            ExportColumn::make('hierarchy_type')
                ->state(static fn (AddressArea $record): ?string => self::ancestorPivotValue($record, 'hierarchy_type')),
            ExportColumn::make('source')
                ->label('Source'),
            ExportColumn::make('source_id')
                ->label('Source ID'),
            ExportColumn::make('latitude')
                ->label('Latitude'),
            ExportColumn::make('longitude')
                ->label('Longitude'),
            ExportColumn::make('metadata')
                ->formatStateUsing(static fn (mixed $state): string => is_array($state)
                    ? (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                    : (string) ($state ?? '')),
            ExportColumn::make('source_payload')
                ->label('Source Payload')
                ->formatStateUsing(static fn (mixed $state): string => is_array($state)
                    ? (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                    : (string) ($state ?? '')),
            ExportColumn::make('synced_at')
                ->label('Synced At'),
            ExportColumn::make('ancestors.id')
                ->hidden()
                ->state(static fn (): null => null),
        ];
    }

    private static function ancestorPivotValue(AddressArea $record, string $column): ?string
    {
        $record->loadMissing('ancestors');

        $ancestor = $record->getRelation('ancestors')->first(
            static fn (AddressArea $candidate): bool => self::pivotValue($candidate, 'source') === (string) $record->source,
        );

        if (! $ancestor instanceof AddressArea) {
            return null;
        }

        return self::pivotValue($ancestor, $column);
    }

    private static function pivotValue(AddressArea $area, string $column): ?string
    {
        $pivot = $area->getRelation('pivot');
        $value = $pivot instanceof Pivot ? $pivot->getAttribute($column) : null;

        return is_string($value) ? $value : null;
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
