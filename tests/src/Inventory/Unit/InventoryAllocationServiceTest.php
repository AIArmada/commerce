<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAllocated;
use AIArmada\Inventory\Events\InventoryReleased;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryAllocationService;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\Event;

class InventoryAllocationServiceTest extends InventoryTestCase
{
    protected InventoryService $inventoryService;
    protected InventoryAllocationService $allocationService;
    protected InventoryItem $item;
    protected InventoryLocation $locationA;
    protected InventoryLocation $locationB;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_allocates_across_locations_using_split_allocation_and_updates_reserved_quantities(): void
    {
        Event::fake();

        $allocations = $this->allocationService->allocate($this->item, 12, 'cart-1', 45);

        expect($allocations)->toHaveCount(2);
        expect($allocations->sum('quantity'))->toBe(12);

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id)?->fresh();

        expect($levelA?->quantity_reserved)->toBe(10);
        expect($levelB?->quantity_reserved)->toBe(2);

        Event::assertDispatched(InventoryAllocated::class);
    }

    public function test_releases_allocations_and_restores_reserved_quantities(): void
    {
        Event::fake();

        $this->allocationService->allocate($this->item, 5, 'cart-2');

        $released = $this->allocationService->release($this->item, 'cart-2');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id)?->fresh();

        expect($released)->toBe(5);
        expect($levelA?->quantity_reserved)->toBe(0);
        expect($levelB?->quantity_reserved)->toBe(0);

        Event::assertDispatched(InventoryReleased::class);
    }

    public function test_commits_allocations_into_shipments_and_clears_reservations(): void
    {
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
    }

    public function test_cleans_up_expired_allocations_and_frees_reserved_stock(): void
    {
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
    }
}
