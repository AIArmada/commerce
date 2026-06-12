<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\FilamentAddressing\Exports\AddressAreaExporter;
use AIArmada\FilamentAddressing\Imports\AddressAreaImporter;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

final class ListAddressAreas extends ListRecords
{
    protected static string $resource = AddressAreaResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (config('filament-addressing.features.area_import')) {
            $actions[] = ImportAction::make()
                ->importer(AddressAreaImporter::class)
                ->label('Import Areas');
        }

        if (config('filament-addressing.features.area_export')) {
            $actions[] = ExportAction::make()
                ->exporter(AddressAreaExporter::class)
                ->label('Export Areas');
        }

        return $actions;
    }
}
