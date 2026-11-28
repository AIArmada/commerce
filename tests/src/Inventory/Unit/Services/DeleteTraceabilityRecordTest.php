<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Traceability\Actions\DeleteTraceabilityRecord;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use AIArmada\Inventory\Features\Traceability\Persistence\SerialService;

it('preserves stock and rejects deletion even with stale batch balances', function (): void {
    $batch = InventoryBatch::factory()->create([
        'quantity_received' => 0,
        'quantity_on_hand' => 0,
        'quantity_reserved' => 0,
    ]);
    $stale = $batch->fresh();
    $batch->update(['quantity_received' => 10, 'quantity_on_hand' => 10]);

    expect(app(DeleteTraceabilityRecord::class)->execute($stale))->toBeFalse()
        ->and($batch->fresh()->quantity_on_hand)->toBe(10);
});

it('preserves serial registration history and rejects deletion of sold serials', function (): void {
    $item = InventoryItem::create(['name' => 'Traceable item']);
    $location = InventoryLocation::factory()->create();
    $serial = app(SerialService::class)->register($item, 'SERIAL-PRESERVE', $location->id);
    $sold = InventorySerial::factory()->sold()->create();
    $action = app(DeleteTraceabilityRecord::class);

    expect($action->execute($serial))->toBeFalse()
        ->and($action->execute($sold))->toBeFalse()
        ->and($serial->delete())->toBeFalse()
        ->and($sold->delete())->toBeFalse()
        ->and($serial->history()->count())->toBe(1);
});

it('permits deletion of unused traceability records', function (): void {
    $batch = InventoryBatch::factory()->create([
        'quantity_received' => 0,
        'quantity_on_hand' => 0,
        'quantity_reserved' => 0,
    ]);
    $serial = InventorySerial::factory()->create();
    $action = app(DeleteTraceabilityRecord::class);

    expect($action->execute($batch))->toBeTrue()
        ->and($action->execute($serial))->toBeTrue()
        ->and($batch->fresh())->toBeNull()
        ->and($serial->fresh())->toBeNull();
});
