<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\FilamentInventory\Actions\AdjustStockAction;
use AIArmada\FilamentInventory\Actions\CycleCountAction;
use AIArmada\FilamentInventory\Actions\ReceiveStockAction;
use AIArmada\FilamentInventory\Actions\ShipStockAction;
use AIArmada\FilamentInventory\Actions\TransferStockAction;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;

beforeEach(function (): void {
    config()->set('inventory.owner.enabled', false);
    config()->set('filament-inventory.cache.stats_ttl', 0);
});

it('executes receive stock action and creates inventory', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    $action = ReceiveStockAction::make();

    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    $callback($item, [
        'location_id' => $location->id,
        'quantity' => 5,
        'purchase_order' => 'PO-1',
        'supplier' => 'ACME',
        'received_at' => now(),
        'notes' => 'ok',
    ]);

    $level = InventoryLevel::query()
        ->where('inventoryable_type', $item->getMorphClass())
        ->where('inventoryable_id', $item->getKey())
        ->where('location_id', $location->id)
        ->first();

    expect($level)->not()->toBeNull();
    expect((int) $level?->quantity_on_hand)->toBe(5);
});

it('executes ship stock action with insufficient stock without throwing', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    $action = ShipStockAction::make();

    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    $callback($item, [
        'location_id' => $location->id,
        'quantity' => 10,
        'order_number' => 'ORD-1',
        'customer' => 'Customer',
        'tracking_number' => 'TRK',
        'shipped_at' => now(),
        'notes' => 'ship',
    ]);

    expect(true)->toBeTrue();
});

it('executes transfer stock action and moves stock between locations', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $from = InventoryLocation::factory()->create(['name' => 'From']);
    $to = InventoryLocation::factory()->create(['name' => 'To']);

    $inventory = app(InventoryService::class);
    $inventory->receive(model: $item, locationId: $from->id, quantity: 10);

    $action = TransferStockAction::make();
    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    $callback($item, [
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
        'quantity' => 3,
        'notes' => 'move',
    ]);

    $fromLevel = InventoryLevel::query()->where('location_id', $from->id)->first();
    $toLevel = InventoryLevel::query()->where('location_id', $to->id)->first();

    expect($fromLevel)->not()->toBeNull();
    expect($toLevel)->not()->toBeNull();

    expect((int) $fromLevel?->quantity_on_hand)->toBe(7);
    expect((int) $toLevel?->quantity_on_hand)->toBe(3);
});

it('executes adjust stock action and sets new quantity', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    $inventory = app(InventoryService::class);
    $inventory->receive(model: $item, locationId: $location->id, quantity: 10);

    $action = AdjustStockAction::make();

    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    $callback($item, [
        'location_id' => $location->id,
        'new_quantity' => 4,
        'reason' => 'correction',
        'notes' => 'adjust',
    ]);

    $level = InventoryLevel::query()->where('location_id', $location->id)->first();
    expect((int) $level?->quantity_on_hand)->toBe(4);
});

it('executes cycle count action for both no-variance and variance cases', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    $inventory = app(InventoryService::class);
    $inventory->receive(model: $item, locationId: $location->id, quantity: 10);

    $action = CycleCountAction::make();
    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    // variance 0 branch
    $callback($item, [
        'location_id' => $location->id,
        'system_quantity' => 10,
        'counted_quantity' => 10,
        'counter' => 'Alice',
    ]);

    // variance branch (triggers adjustment)
    $callback($item, [
        'location_id' => $location->id,
        'system_quantity' => 10,
        'counted_quantity' => 8,
        'counter' => 'Bob',
    ]);

    $level = InventoryLevel::query()->where('location_id', $location->id)->first();
    expect((int) $level?->quantity_on_hand)->toBe(8);
});
