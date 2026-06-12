<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages;

use AIArmada\FilamentAddressing\Exports\AddressCountryExporter;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

final class ListAddressCountries extends ListRecords
{
    protected static string $resource = AddressCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(AddressCountryExporter::class)
                ->label('Export Countries'),
        ];
    }
}
