<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Exports;

use AIArmada\Contacting\Models\SocialProfile;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

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
}
