<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewInventoryBatch extends ViewRecord
{
    protected static string $resource = InventoryBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
