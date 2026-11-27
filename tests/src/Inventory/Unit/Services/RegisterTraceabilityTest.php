<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Traceability\Actions\RegisterTraceability;
use AIArmada\Inventory\States\Available;

it('registers traceability records against an existing inventory identity', function (): void {
    $item = InventoryItem::create(['name' => 'Traceable item']);
    $location = InventoryLocation::factory()->create();
    $level = InventoryLevel::factory()->create([
        'inventoryable_type' => $item->getMorphClass(),
        'inventoryable_id' => $item->getKey(),
        'location_id' => $location->id,
        'quantity_on_hand' => 20,
    ]);

    $action = app(RegisterTraceability::class);
    $batch = $action->batch($level->id, [
        'batch_number' => 'LOT-REGISTRATION',
        'quantity_received' => 10,
        'quantity_reserved' => 9,
        'quantity_on_hand' => 999,
        'status' => 'recalled',
    ]);
    $serial = $action->serial($level->id, [
        'serial_number' => 'SN-REGISTRATION',
        'batch_id' => $batch->id,
        'status' => 'sold',
    ]);

    expect($batch->quantity_on_hand)->toBe(10)
        ->and($batch->quantity_reserved)->toBe(0)
        ->and($batch->inventoryable_id)->toBe($item->getKey())
        ->and($serial->status)->toBeInstanceOf(Available::class)
        ->and($serial->location_id)->toBe($location->id)
        ->and($serial->history()->count())->toBe(1)
        ->and($level->fresh()->quantity_on_hand)->toBe(20);
});
