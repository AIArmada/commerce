<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Events\InventoryAllocated;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

describe('InventoryAllocated', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location1 = InventoryLocation::factory()->create([
            'name' => 'Location 1',
            'code' => 'LOC1',
        ]);
        $this->location2 = InventoryLocation::factory()->create([
            'name' => 'Location 2',
            'code' => 'LOC2',
        ]);
    });

    it('event stores properties correctly', function (): void {
        $allocations = new Collection([
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location1->id,
                'quantity' => 5,
                'cart_id' => 'cart-123',
            ]),
        ]);

        $event = new InventoryAllocated(
            $this->item,
            $allocations,
            'cart-123'
        );

        expect($event->inventoryable)->toBe($this->item);
        expect($event->allocations)->toBe($allocations);
        expect($event->cartId)->toBe('cart-123');
    });

    it('get total quantity sums allocations', function (): void {
        $allocations = new Collection([
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location1->id,
                'quantity' => 5,
                'cart_id' => 'cart-123',
            ]),
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location2->id,
                'quantity' => 3,
                'cart_id' => 'cart-123',
            ]),
        ]);

        $event = new InventoryAllocated($this->item, $allocations, 'cart-123');

        expect($event->getTotalQuantity())->toBe(8);
    });

    it('defers dispatch until after commit', function (): void {
        expect(InventoryAllocated::class)->toImplement(ShouldDispatchAfterCommit::class);
    });

    it('does not notify listeners when the outer transaction rolls back', function (): void {
        $inventoryService = app(InventoryService::class);
        $allocationService = app(InventoryAllocationService::class);

        $inventoryService->receive($this->item, $this->location1->id, 10);

        $seen = 0;

        Event::listen(InventoryAllocated::class, function () use (&$seen): void {
            $seen++;
        });

        try {
            try {
                DB::transaction(function () use ($allocationService): void {
                    $allocationService->allocate($this->item, 2, 'aftercommit-cart');

                    throw new RuntimeException('outer rollback');
                });
            } catch (RuntimeException $e) {
                expect($e->getMessage())->toBe('outer rollback');
            }

            expect($seen)->toBe(0);
        } finally {
            Event::forget(InventoryAllocated::class);
        }
    });

    it('get location count returns unique locations', function (): void {
        $allocations = new Collection([
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location1->id,
                'quantity' => 5,
                'cart_id' => 'cart-123',
            ]),
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location1->id,
                'quantity' => 3,
                'cart_id' => 'cart-123',
            ]),
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location2->id,
                'quantity' => 2,
                'cart_id' => 'cart-123',
            ]),
        ]);

        $event = new InventoryAllocated($this->item, $allocations, 'cart-123');

        expect($event->getLocationCount())->toBe(2);
    });
});
