<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\FilamentInventory\Actions\CycleCountAction;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;

beforeEach(function (): void {
    config()->set('inventory.owner.enabled', false);
});

it('recomputes the system quantity server side without a submitted value', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    app(InventoryService::class)->receive(model: $item, locationId: $location->id, quantity: 10);

    $callback = CycleCountAction::make()->getActionFunction();
    expect($callback)->not()->toBeNull();

    $callback($item, [
        'location_id' => $location->id,
        'counted_quantity' => 12,
    ]);

    $level = InventoryLevel::query()
        ->where('inventoryable_type', $item->getMorphClass())
        ->where('inventoryable_id', $item->getKey())
        ->where('location_id', $location->id)
        ->first();

    expect((int) $level?->quantity_on_hand)->toBe(12);
});

it('verifies the count without adjustment when quantities match', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    app(InventoryService::class)->receive(model: $item, locationId: $location->id, quantity: 7);

    $callback = CycleCountAction::make()->getActionFunction();

    $callback($item, [
        'location_id' => $location->id,
        'counted_quantity' => 7,
    ]);

    $level = InventoryLevel::query()
        ->where('inventoryable_type', $item->getMorphClass())
        ->where('inventoryable_id', $item->getKey())
        ->where('location_id', $location->id)
        ->first();

    expect((int) $level?->quantity_on_hand)->toBe(7);
});
