<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLevelResource\Pages;

use AIArmada\FilamentInventory\Resources\InventoryLevelResource;
use AIArmada\Inventory\Features\Stock\Actions\UpdateInventoryLevel;
use AIArmada\Inventory\Models\InventoryLevel;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class EditInventoryLevel extends EditRecord
{
    protected static string $resource = InventoryLevelResource::class;

    protected function handleRecordUpdate(Model $record, array $data): InventoryLevel
    {
        abort_unless($record instanceof InventoryLevel, 404);

        try {
            return app(UpdateInventoryLevel::class)->execute($record, $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['data.quantity_on_hand' => $exception->getMessage()]);
        }
    }
}
