<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Traceability\Actions\RegisterTraceability;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use AIArmada\Inventory\States\Available;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

it('rejects serial registration against a batch at a different inventory location', function (): void {
    $item = InventoryItem::create(['name' => 'Traceable item']);
    $levels = InventoryLevel::factory()->count(2)->create([
        'inventoryable_type' => $item->getMorphClass(),
        'inventoryable_id' => $item->getKey(),
    ]);
    $action = app(RegisterTraceability::class);
    $batch = $action->batch($levels[0]->id, [
        'batch_number' => 'LOT-OTHER-LOCATION',
        'quantity_received' => 10,
    ]);

    expect(fn () => $action->serial($levels[1]->id, [
        'serial_number' => 'SN-WRONG-LOCATION',
        'batch_id' => $batch->id,
    ]))->toThrow(InvalidArgumentException::class, 'Batch does not match the serial item or location')
        ->and(InventorySerial::query()->where('serial_number', 'SN-WRONG-LOCATION')->exists())->toBeFalse();
});

it('cannot register traceability against a tenant-visible global stock level', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $owner = InventoryItem::create(['name' => 'Tenant']);
    $item = InventoryItem::create(['name' => 'Traceable global item']);

    $level = OwnerContext::withOwner(null, function () use ($item): InventoryLevel {
        $location = InventoryLocation::factory()->create();

        return InventoryLevel::factory()->create([
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => 10,
        ]);
    });

    OwnerContext::withOwner($owner, function () use ($level): void {
        $action = app(RegisterTraceability::class);

        expect(fn () => $action->serial($level->id, ['serial_number' => 'SN-GLOBAL']))
            ->toThrow(ModelNotFoundException::class)
            ->and(fn () => $action->batch($level->id, ['batch_number' => 'LOT-GLOBAL', 'quantity_received' => 1]))
            ->toThrow(ModelNotFoundException::class);
    });
});
