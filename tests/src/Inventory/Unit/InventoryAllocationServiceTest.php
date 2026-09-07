<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAllocated;
use AIArmada\Inventory\Events\InventoryReleased;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Illuminate\Support\Facades\Event;

describe('InventoryAllocationService', function (): void {
    beforeEach(function (): void {
        config()->set('inventory.allow_split_allocation', true);
        config()->set('inventory.allocation_strategy', 'priority');

        $this->inventoryService = app(InventoryService::class);
        $this->allocationService = app(InventoryAllocationService::class);

        $this->item = InventoryItem::create(['name' => 'Allocatable Item']);

        $this->locationA = InventoryLocation::factory()->create([
            'name' => 'A',
            'code' => 'LOC-A',
            'priority' => 90,
        ]);

        $this->locationB = InventoryLocation::factory()->create([
            'name' => 'B',
            'code' => 'LOC-B',
            'priority' => 50,
        ]);

        $this->inventoryService->receive($this->item, $this->locationA->id, 10);
        $this->inventoryService->receive($this->item, $this->locationB->id, 6);
    });

    it('allocates across locations using split allocation and updates reserved quantities', function (): void {
        Event::fake();

        $allocations = $this->allocationService->allocate($this->item, 12, 'cart-1', 45);

        expect($allocations)->toHaveCount(2);
        expect($allocations->sum('quantity'))->toBe(12);

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id)?->fresh();

        expect($levelA?->quantity_reserved)->toBe(10);
        expect($levelB?->quantity_reserved)->toBe(2);

        Event::assertDispatched(InventoryAllocated::class);
    });

    it('releases allocations and restores reserved quantities', function (): void {
        Event::fake();

        $this->allocationService->allocate($this->item, 5, 'cart-2');

        $released = $this->allocationService->release($this->item, 'cart-2');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id)?->fresh();

        expect($released)->toBe(5);
        expect($levelA?->quantity_reserved)->toBe(0);
        expect($levelB?->quantity_reserved)->toBe(0);

        Event::assertDispatched(InventoryReleased::class);
    });

    it('commits allocations into shipments and clears reservations', function (): void {
        $allocations = $this->allocationService->allocate($this->item, 8, 'cart-3');

        $movements = $this->allocationService->commit('cart-3', 'ORDER-123');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id)?->fresh();

        expect($movements)->toHaveCount($allocations->count());
        expect($movements[0]->type)->toBe(MovementType::Shipment->value);
        expect($levelA?->quantity_reserved)->toBe(0);
        expect($levelB?->quantity_reserved)->toBe(0);
        expect($levelA?->quantity_on_hand + $levelB?->quantity_on_hand)->toBe(8); // 16 received - 8 committed
        expect(InventoryAllocation::query()->forCart('cart-3')->count())->toBe(0);
    });

    it('cleans up expired allocations and frees reserved stock', function (): void {
        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);

        $allocation = InventoryAllocation::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->locationA->id,
            'level_id' => $level?->id,
            'cart_id' => 'expired-cart',
            'quantity' => 3,
            'expires_at' => now()->subMinute(),
        ]);

        $level?->incrementReserved($allocation->quantity);

        $removed = $this->allocationService->cleanupExpired();

        $level?->refresh();

        expect($removed)->toBe(1);
        expect($level?->quantity_reserved)->toBe(0);
        expect(InventoryAllocation::query()->forCart('expired-cart')->count())->toBe(0);
    });

    it('throws exception for zero or negative quantity', function (): void {
        expect(fn () => $this->allocationService->allocate($this->item, 0, 'cart-1'))
            ->toThrow(InvalidArgumentException::class, 'Quantity must be positive');

        expect(fn () => $this->allocationService->allocate($this->item, -5, 'cart-1'))
            ->toThrow(InvalidArgumentException::class, 'Quantity must be positive');
    });

    it('throws exception when insufficient inventory', function (): void {
        expect(fn () => $this->allocationService->allocate($this->item, 100, 'cart-1'))
            ->toThrow(InsufficientInventoryException::class, 'Insufficient inventory');
    });

    it('releases existing allocations before new allocation', function (): void {
        $this->allocationService->allocate($this->item, 5, 'cart-realloc');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        expect($levelA?->quantity_reserved)->toBe(5);

        // Allocate again with same cart - should release first
        $this->allocationService->allocate($this->item, 3, 'cart-realloc');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        expect($levelA?->quantity_reserved)->toBe(3);
    });

    it('release all for cart releases all allocations', function (): void {
        // Create a second item and allocations
        $item2 = InventoryItem::create(['name' => 'Second Item']);
        $this->inventoryService->receive($item2, $this->locationA->id, 10);

        $this->allocationService->allocate($this->item, 5, 'cart-release-all');
        $this->allocationService->allocate($item2, 3, 'cart-release-all');

        expect(InventoryAllocation::query()->forCart('cart-release-all')->count())->toBe(2);

        $released = $this->allocationService->releaseAllForCart('cart-release-all');

        expect($released)->toBe(8);
        expect(InventoryAllocation::query()->forCart('cart-release-all')->count())->toBe(0);
    });

    it('extend allocations updates expiry', function (): void {
        $this->allocationService->allocate($this->item, 5, 'cart-extend', 10);

        $originalExpiry = InventoryAllocation::query()
            ->forCart('cart-extend')
            ->first()
            ?->expires_at;

        $updated = $this->allocationService->extendAllocations('cart-extend', 60);

        expect($updated)->toBe(1);

        $newExpiry = InventoryAllocation::query()
            ->forCart('cart-extend')
            ->first()
            ?->expires_at;

        expect($newExpiry->gt($originalExpiry))->toBeTrue();
    });

    it('get allocations for cart returns active allocations', function (): void {
        $this->allocationService->allocate($this->item, 5, 'cart-get');

        $allocations = $this->allocationService->getAllocationsForCart('cart-get');

        expect($allocations)->toHaveCount(1);
    });

    it('get allocations for model and cart', function (): void {
        $this->allocationService->allocate($this->item, 5, 'cart-model');

        $allocations = $this->allocationService->getAllocations($this->item, 'cart-model');

        expect($allocations)->toHaveCount(1);
    });

    it('has available inventory', function (): void {
        expect($this->allocationService->hasAvailableInventory($this->item, 10))->toBeTrue();
        expect($this->allocationService->hasAvailableInventory($this->item, 16))->toBeTrue();
        expect($this->allocationService->hasAvailableInventory($this->item, 17))->toBeFalse();
    });

    it('get total available', function (): void {
        $available = $this->allocationService->getTotalAvailable($this->item);

        expect($available)->toBe(16); // 10 + 6 from setup
    });

    it('validate availability returns available true when all items available', function (): void {
        $result = $this->allocationService->validateAvailability([
            ['model' => $this->item, 'quantity' => 5],
        ]);

        expect($result['available'])->toBeTrue();
        expect($result['issues'])->toBeEmpty();
    });

    it('validate availability returns issues when insufficient', function (): void {
        $result = $this->allocationService->validateAvailability([
            ['model' => $this->item, 'quantity' => 50],
        ]);

        expect($result['available'])->toBeFalse();
        expect($result['issues'])->toHaveCount(1);
        expect($result['issues'][0]['requested'])->toBe(50);
        expect($result['issues'][0]['available'])->toBe(16);
    });

    it('get strategy returns default from config', function (): void {
        config()->set('inventory.allocation_strategy', 'fifo');

        $strategy = $this->allocationService->getStrategy($this->item);

        expect($strategy)->toBe(AllocationStrategy::FIFO);
    });

    it('release allocation releases single allocation', function (): void {
        Event::fake();

        $allocations = $this->allocationService->allocate($this->item, 5, 'cart-single-release');
        $allocation = $allocations->first();

        $released = $this->allocationService->releaseAllocation($allocation);

        expect($released)->toBe(5);
        expect(InventoryAllocation::find($allocation->id))->toBeNull();

        Event::assertDispatched(InventoryReleased::class);
    });

    it('single location strategy does not split', function (): void {
        config()->set('inventory.allocation_strategy', 'single_location');

        $allocations = $this->allocationService->allocate($this->item, 8, 'cart-single-loc');

        expect($allocations)->toHaveCount(1);
        expect($allocations->first()->quantity)->toBe(8);
    });

    it('allocate uses fifo strategy', function (): void {
        config()->set('inventory.allocation_strategy', 'fifo');

        // Location A was created first, so it should be used first
        $allocations = $this->allocationService->allocate($this->item, 5, 'cart-fifo');

        expect($allocations)->toHaveCount(1);
        expect($allocations->first()->location_id)->toBe($this->locationA->id);
    });

    it('allocate uses least stock strategy', function (): void {
        config()->set('inventory.allocation_strategy', 'least_stock');

        // LeastStock allocates from locations with MOST available stock first to balance
        $allocations = $this->allocationService->allocate($this->item, 5, 'cart-least');

        // Location A has 10, Location B has 6, so A should be used first
        expect($allocations->first()->location_id)->toBe($this->locationA->id);
    });

    it('commit dispatches out of inventory event when stock depleted', function (): void {
        Event::fake();
        config()->set('inventory.events.low_inventory', true);
        config()->set('inventory.events.out_of_inventory', true);

        // Allocate and commit all stock from location A
        $this->allocationService->allocate($this->item, 10, 'cart-deplete');
        $this->allocationService->commit('cart-deplete', 'ORD-DEPLETE');

        Event::assertDispatched(OutOfInventory::class);
    });

    it('commit dispatches low inventory event when below threshold', function (): void {
        Event::fake();
        config()->set('inventory.events.low_inventory', true);

        // Set low stock threshold
        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);
        $level->update(['reorder_point' => 5]);

        // Allocate 8 of 10, leaving 2 which is below reorder_point of 5
        $this->allocationService->allocate($this->item, 8, 'cart-low');
        $this->allocationService->commit('cart-low', 'ORD-LOW');

        Event::assertDispatched(LowInventoryDetected::class);
    });

    it('split allocation disabled requires single location', function (): void {
        config()->set('inventory.allow_split_allocation', false);

        // Can't allocate 12 because neither location has enough (A has 10, B has 6)
        expect(fn () => $this->allocationService->allocate($this->item, 12, 'cart-no-split'))
            ->toThrow(InsufficientInventoryException::class, 'Insufficient inventory');
    });

    it('release returns zero when no allocations', function (): void {
        $released = $this->allocationService->release($this->item, 'non-existent-cart');

        expect($released)->toBe(0);
    });
});
