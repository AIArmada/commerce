<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventoryBatch extends CreateRecord
{
    protected static string $resource = InventoryBatchResource::class;
}
