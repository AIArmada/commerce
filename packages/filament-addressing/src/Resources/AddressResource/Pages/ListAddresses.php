<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressResource\Pages;

use AIArmada\FilamentAddressing\Exports\AddressExporter;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

final class ListAddresses extends ListRecords
{
    protected static string $resource = AddressResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make()
                ->visible(fn (): bool => ! AddressResource::isReadOnly() && AddressResource::canCreate()),
        ];

        if (config('filament-addressing.features.address_export')) {
            $actions[] = ExportAction::make()
                ->exporter(AddressExporter::class)
                ->label('Export Addresses');
        }

        return $actions;
    }
}
