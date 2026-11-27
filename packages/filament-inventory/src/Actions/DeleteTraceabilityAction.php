<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\Inventory\Features\Traceability\Actions\DeleteTraceabilityRecord;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use Filament\Actions\DeleteAction;

final class DeleteTraceabilityAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->using(static fn (InventoryBatch | InventorySerial $record): bool => app(DeleteTraceabilityRecord::class)->execute($record));
    }
}
