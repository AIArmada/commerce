<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages;

use AIArmada\FilamentAddressing\Exports\PostalCodeExporter;
use AIArmada\FilamentAddressing\Imports\PostalCodeImporter;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

final class ListPostalCodes extends ListRecords
{
    protected static string $resource = PostalCodeResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (! PostalCodeResource::isReadOnly() && PostalCodeResource::canCreate()) {
            $actions[] = CreateAction::make();
        }

        if (config('filament-addressing.features.postal_code_import', false)) {
            $actions[] = ImportAction::make()
                ->importer(PostalCodeImporter::class)
                ->label('Import Postcodes')
                ->visible(fn (): bool => ! PostalCodeResource::isReadOnly() && PostalCodeResource::canCreate())
                ->authorize(fn (): bool => ! PostalCodeResource::isReadOnly() && PostalCodeResource::canCreate());
        }

        if (config('filament-addressing.features.postal_code_export', false)) {
            $actions[] = ExportAction::make()
                ->exporter(PostalCodeExporter::class)
                ->label('Export Postcodes');
        }

        return $actions;
    }
}
