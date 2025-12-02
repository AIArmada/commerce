<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLevelResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryLevelResource;
use Filament\Resources\Pages\EditRecord;

final class EditInventoryLevel extends EditRecord
{
    protected static string $resource = InventoryLevelResource::class;
}
