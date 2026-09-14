<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;

beforeEach(function (): void {
    config()->set('inventory.owner.enabled', false);
});

it('preloads location statistics with the view query', function (): void {
    $location = InventoryLocation::factory()->create(['is_active' => true]);
    $item = InventoryItem::create(['name' => 'Widget']);

    app(InventoryService::class)->receive(model: $item, locationId: $location->id, quantity: 10);

    $record = InventoryLocationResource::getRecordRouteBindingEloquentQuery()
        ->whereKey($location->id)
        ->firstOrFail();

    expect($record->getAttribute('inventory_levels_count'))->toBe(1)
        ->and((int) $record->getAttribute('inventory_levels_sum_quantity_on_hand'))->toBe(10)
        ->and($record->getAttribute('inventory_levels_sum_quantity_reserved'))->not->toBeNull();
});
