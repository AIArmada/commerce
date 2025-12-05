<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages;

use AIArmada\FilamentInventory\Resources\InventorySerialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListInventorySerials extends ListRecords
{
    protected static string $resource = InventorySerialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
