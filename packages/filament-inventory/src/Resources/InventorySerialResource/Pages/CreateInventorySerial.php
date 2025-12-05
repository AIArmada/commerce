<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages;

use AIArmada\FilamentInventory\Resources\InventorySerialResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventorySerial extends CreateRecord
{
    protected static string $resource = InventorySerialResource::class;
}
