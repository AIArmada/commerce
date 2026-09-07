<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

describe('InventoryLevel', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    });

    it('can create inventory level', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
        ]);

        expect($level)->toBeInstanceOf(InventoryLevel::class);
        expect($level->quantity_on_hand)->toBe(100);
        expect($level->quantity_reserved)->toBe(20);
    });

    it('available attribute calculates correctly', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 100,
            'quantity_reserved' => 30,
        ]);

        expect($level->available)->toBe(70);
    });

    it('available never goes below zero', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 50,
        ]);

        expect($level->available)->toBe(0);
    });

    it('get available quantity method', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
        ]);

        expect($level->getAvailableQuantity())->toBe(40);
    });

    it('is low stock returns true when below threshold', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        expect($level->isLowStock())->toBeTrue();
    });

    it('is low stock returns false when above threshold', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        expect($level->isLowStock())->toBeFalse();
    });

    it('is low stock uses custom threshold', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 15,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        expect($level->isLowStock(20))->toBeTrue();
        expect($level->isLowStock(10))->toBeFalse();
    });

    it('is safety stock breached returns true when below', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);

        expect($level->isSafetyStockBreached())->toBeTrue();
    });

    it('is safety stock breached returns false when no safety stock', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => null,
        ]);

        expect($level->isSafetyStockBreached())->toBeFalse();
    });

    it('is over stocked returns true when above max', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 150,
            'max_stock' => 100,
        ]);

        expect($level->isOverStocked())->toBeTrue();
    });

    it('is over stocked returns false when no max stock', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 1000,
            'max_stock' => null,
        ]);

        expect($level->isOverStocked())->toBeFalse();
    });

    it('get alert status enum returns none when null', function (): void {
        $level = InventoryLevel::factory()->create([
            'alert_status' => null,
        ]);

        expect($level->getAlertStatusEnum())->toBe(AlertStatus::None);
    });

    it('get alert status enum returns correct status', function (): void {
        $level = InventoryLevel::factory()->create([
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        expect($level->getAlertStatusEnum())->toBe(AlertStatus::LowStock);
    });

    it('has available returns true when sufficient', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
        ]);

        expect($level->hasAvailable(30))->toBeTrue();
        expect($level->hasAvailable(40))->toBeTrue();
    });

    it('has available returns false when insufficient', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
        ]);

        expect($level->hasAvailable(50))->toBeFalse();
    });

    it('get effective allocation strategy uses own strategy', function (): void {
        $level = InventoryLevel::factory()->create([
            'allocation_strategy' => AllocationStrategy::FIFO->value,
        ]);

        expect($level->getEffectiveAllocationStrategy())->toBe(AllocationStrategy::FIFO);
    });

    it('get effective allocation strategy with allocation strategy set', function (): void {
        $level = InventoryLevel::factory()->create([
            'allocation_strategy' => AllocationStrategy::LeastStock->value,
        ]);

        expect($level->getEffectiveAllocationStrategy())->toBe(AllocationStrategy::LeastStock);
    });

    it('get effective allocation strategy uses config when null', function (): void {
        config()->set('inventory.allocation_strategy', 'priority');

        $level = InventoryLevel::factory()->create([
            'allocation_strategy' => null,
        ]);

        expect($level->getEffectiveAllocationStrategy())->toBe(AllocationStrategy::Priority);
    });

    it('increment on hand', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 100,
        ]);

        $level->incrementOnHand(25);

        expect($level->fresh()->quantity_on_hand)->toBe(125);
    });

    it('decrement on hand', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 100,
        ]);

        $level->decrementOnHand(30);

        expect($level->fresh()->quantity_on_hand)->toBe(70);
    });

    it('increment reserved', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_reserved' => 20,
        ]);

        $level->incrementReserved(10);

        expect($level->fresh()->quantity_reserved)->toBe(30);
    });

    it('decrement reserved', function (): void {
        $level = InventoryLevel::factory()->create([
            'quantity_reserved' => 30,
        ]);

        $level->decrementReserved(15);

        expect($level->fresh()->quantity_reserved)->toBe(15);
    });

    it('scope at location', function (): void {
        $location2 = InventoryLocation::factory()->create();

        InventoryLevel::factory()->create(['location_id' => $this->location->id]);
        InventoryLevel::factory()->create(['location_id' => $this->location->id]);
        InventoryLevel::factory()->create(['location_id' => $location2->id]);

        $levels = InventoryLevel::atLocation($this->location->id)->get();

        expect($levels)->toHaveCount(2);
    });

    it('scope low stock', function (): void {
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $lowStockLevels = InventoryLevel::lowStock(10)->get();

        expect($lowStockLevels)->toHaveCount(1);
    });

    it('scope with available', function (): void {
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 5,
        ]);
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 5,
        ]);

        $available = InventoryLevel::withAvailable(3)->get();

        expect($available)->toHaveCount(1);
    });

    it('scope with alert status', function (): void {
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::LowStock->value]);
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::None->value]);

        $lowStock = InventoryLevel::withAlertStatus(AlertStatus::LowStock)->get();

        expect($lowStock)->toHaveCount(1);
    });

    it('scope needs reorder', function (): void {
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::LowStock->value]);
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::SafetyBreached->value]);
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::OutOfStock->value]);
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::None->value]);

        $needsReorder = InventoryLevel::needsReorder()->get();

        expect($needsReorder)->toHaveCount(3);
    });

    it('scope safety stock breached', function (): void {
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);

        $breached = InventoryLevel::safetyStockBreached()->get();

        expect($breached)->toHaveCount(1);
    });

    it('scope over stocked', function (): void {
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 150,
            'max_stock' => 100,
        ]);
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'max_stock' => 100,
        ]);

        $overStocked = InventoryLevel::overStocked()->get();

        expect($overStocked)->toHaveCount(1);
    });

    it('location relationship', function (): void {
        $level = InventoryLevel::factory()->create([
            'location_id' => $this->location->id,
        ]);

        expect($level->location)->not->toBeNull();
        expect($level->location->id)->toBe($this->location->id);
    });

    it('inventoryable relationship', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        expect($level->inventoryable)->not->toBeNull();
        expect($level->inventoryable->id)->toBe($this->item->id);
    });

    it('allocations relationship', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);

        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        expect($level->allocations)->toHaveCount(1);
    });

    it('deleting level cascades to allocations', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);

        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        $level->delete();

        expect(InventoryAllocation::find($allocation->id))->toBeNull();
    });
});
