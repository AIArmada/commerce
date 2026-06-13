<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Exports;

use AIArmada\Contacting\Models\SocialProfile;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

final class SocialProfileExporter extends Exporter
{
    protected static ?string $model = SocialProfile::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('socialable_type'),
            ExportColumn::make('socialable_id'),
            ExportColumn::make('platform'),
            ExportColumn::make('label'),
            ExportColumn::make('handle'),
            ExportColumn::make('url'),
            ExportColumn::make('display_name'),
            ExportColumn::make('is_primary'),
            ExportColumn::make('is_public'),
            ExportColumn::make('is_verified'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your social profile export has completed and '
            . number_format($export->successful_rows) . ' '
            . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
