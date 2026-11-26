<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Exporter;

use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\Events\Features\Venues\Models\Venue;
use AIArmada\Events\Platform\ModelResolver;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class VenueExporter extends Exporter
{
    /**
     * @return class-string<Venue>
     */
    public static function getModel(): string
    {
        /** @var class-string<Venue> $model */
        $model = ModelResolver::venueClass();

        return $model;
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('venue_type'),
            ExportColumn::make('line1')
                ->state(static fn (Venue $record): ?string => static::address($record)?->line1),
            ExportColumn::make('city')
                ->state(static fn (Venue $record): ?string => static::address($record)?->city),
            ExportColumn::make('state')
                ->state(static fn (Venue $record): ?string => static::address($record)?->state),
            ExportColumn::make('country_code')
                ->label('Country')
                ->state(static fn (Venue $record): ?string => static::address($record)?->country_code),
            ExportColumn::make('status'),
            ExportColumn::make('phone'),
            ExportColumn::make('email'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your venue export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }

    private static function address(Venue $venue): ?Address
    {
        return $venue->primaryAddress();
    }
}
