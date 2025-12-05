<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditInventoryBatch extends EditRecord
{
    protected static string $resource = InventoryBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
