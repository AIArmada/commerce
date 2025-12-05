<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages;

use AIArmada\FilamentInventory\Resources\InventorySerialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditInventorySerial extends EditRecord
{
    protected static string $resource = InventorySerialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
