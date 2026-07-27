<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages;

use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

final class ListPostalCodes extends ListRecords
{
    protected static string $resource = PostalCodeResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (config('filament-addressing.features.postal_code_import', false)) {
            $actions[] = ImportAction::make()
                ->label('Import Postcodes');
        }

        if (config('filament-addressing.features.postal_code_export', false)) {
            $actions[] = ExportAction::make()
                ->label('Export Postcodes');
        }

        return $actions;
    }
}
