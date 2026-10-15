<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\FilamentAddressing\Exports\AddressAreaExporter;
use AIArmada\FilamentAddressing\Imports\AddressAreaImporter;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

final class ListAddressAreas extends ListRecords
{
    protected static string $resource = AddressAreaResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (! AddressAreaResource::isReadOnly() && AddressAreaResource::canCreate()) {
            $actions[] = CreateAction::make();
        }

        if (config('filament-addressing.features.area_import')) {
            $actions[] = ImportAction::make()
                ->importer(AddressAreaImporter::class)
                ->label('Import Areas')
                ->visible(fn (): bool => ! AddressAreaResource::isReadOnly())
                ->authorize(fn (): bool => ! AddressAreaResource::isReadOnly());
        }

        if (config('filament-addressing.features.area_export')) {
            $actions[] = ExportAction::make()
                ->exporter(AddressAreaExporter::class)
                ->label('Export Areas');
        }

        return $actions;
    }
}
