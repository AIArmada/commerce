<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryMovementResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryMovementResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewInventoryMovement extends ViewRecord
{
    protected static string $resource = InventoryMovementResource::class;
}
