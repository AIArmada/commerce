<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentInventory\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\FilamentInventory\Fixtures\TestOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentInventory\Services\InventoryStatsAggregator;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('filament-inventory.cache.stats_ttl', 0);

    $activeLocation = InventoryLocation::factory()->create([
        'code' => 'ACTIVE',
        'is_active' => true,
        'priority' => 50,
    ]);

    $inactiveLocation = InventoryLocation::factory()->create([
        'code' => 'INACTIVE',
        'is_active' => false,
        'priority' => 10,
    ]);

    $levelA = InventoryLevel::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-1',
        'location_id' => $activeLocation->id,
        'quantity_on_hand' => 20,
        'quantity_reserved' => 5,
        'reorder_point' => 10,
    ]);

    InventoryLevel::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-2',
        'location_id' => $activeLocation->id,
        'quantity_on_hand' => 3,
        'quantity_reserved' => 1,
        'reorder_point' => 5,
    ]);

    // Movement history within window
    InventoryMovement::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-1',
        'from_location_id' => null,
        'to_location_id' => $activeLocation->id,
        'quantity' => 20,
        'type' => MovementType::Receipt->value,
        'occurred_at' => now()->subDays(2),
    ]);

    InventoryMovement::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-1',
        'from_location_id' => $activeLocation->id,
        'to_location_id' => null,
        'quantity' => 4,
        'type' => MovementType::Shipment->value,
        'occurred_at' => now()->subDay(),
    ]);

    InventoryMovement::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-2',
        'from_location_id' => $activeLocation->id,
        'to_location_id' => $inactiveLocation->id,
        'quantity' => 2,
        'type' => MovementType::Transfer->value,
        'occurred_at' => now()->subHours(6),
    ]);

    InventoryMovement::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-2',
        'from_location_id' => $activeLocation->id,
        'to_location_id' => null,
        'quantity' => 1,
        'type' => MovementType::Adjustment->value,
        'occurred_at' => now()->subHours(3),
    ]);

    InventoryAllocation::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-1',
        'location_id' => $activeLocation->id,
        'level_id' => $levelA->id,
        'cart_id' => 'cart-1',
        'quantity' => 2,
        'expires_at' => now()->addHour(),
    ]);
});

it('returns overview stats including active locations and allocations', function (): void {
    $aggregator = app(InventoryStatsAggregator::class);

    $stats = $aggregator->overview();

    expect($stats['total_locations'])->toBe(2);
    expect($stats['active_locations'])->toBe(1);
    expect($stats['total_skus'])->toBe(2);
    expect($stats['total_on_hand'])->toBe(23);
    expect($stats['total_reserved'])->toBe(6);
    expect($stats['active_allocations'])->toBe(1);
});

it('summarizes movement stats over a period', function (): void {
    $aggregator = app(InventoryStatsAggregator::class);

    $stats = $aggregator->movementStats(7);

    expect($stats['receipts'])->toBe(20);
    expect($stats['shipments'])->toBe(4);
    expect($stats['transfers'])->toBe(2);
    expect($stats['adjustments'])->toBe(1);
    expect($stats['total'])->toBe(4);
});

it('counts low and out-of-stock items using thresholds', function (): void {
    $aggregator = app(InventoryStatsAggregator::class);

    expect($aggregator->lowInventoryCount())->toBe(1);
    expect($aggregator->outOfStockCount())->toBe(0);
});

it('builds cached overview widget stats', function (): void {
    $aggregator = app(InventoryStatsAggregator::class);

    $stats = $aggregator->getOverviewStats();

    expect($stats['active_locations'])->toBe(1);
    expect($stats['total_skus'])->toBe(2);
    expect($stats['total_on_hand'])->toBe(23);
    expect($stats['total_reserved'])->toBe(6);
    expect($stats['total_available'])->toBe(17);
    expect($stats['low_stock_count'])->toBe(1);
});

it('counts distinct skus under owner-scoped location relations without SQL alias errors', function (): void {
    Schema::dropIfExists('filament_inventory_test_owners');

    Schema::create('filament_inventory_test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', false);

    $ownerA = TestOwner::create(['name' => 'Owner A']);
    $ownerB = TestOwner::create(['name' => 'Owner B']);

    $locationA = OwnerContext::withOwner($ownerA, fn (): InventoryLocation => InventoryLocation::factory()->create([
        'code' => 'OWN-A',
        'is_active' => true,
    ]));

    $locationB = OwnerContext::withOwner($ownerB, fn (): InventoryLocation => InventoryLocation::factory()->create([
        'code' => 'OWN-B',
        'is_active' => true,
    ]));

    OwnerContext::withOwner($ownerA, function () use ($locationA): void {
        InventoryLevel::create([
            'inventoryable_type' => 'Product',
            'inventoryable_id' => 'sku-shared',
            'location_id' => $locationA->id,
            'quantity_on_hand' => 11,
            'quantity_reserved' => 1,
            'reorder_point' => 2,
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($locationB): void {
        InventoryLevel::create([
            'inventoryable_type' => 'Product',
            'inventoryable_id' => 'sku-shared',
            'location_id' => $locationB->id,
            'quantity_on_hand' => 7,
            'quantity_reserved' => 0,
            'reorder_point' => 1,
        ]);
    });

    app()->bind(OwnerResolverInterface::class, fn () => new TestOwnerResolver($ownerA));

    $stats = app(InventoryStatsAggregator::class)->getOverviewStats();

    expect($stats['active_locations'])->toBeGreaterThanOrEqual(1)
        ->and($stats['total_skus'])->toBe(1);
});
