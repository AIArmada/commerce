<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\BackorderPriority;
use AIArmada\Inventory\Models\InventoryBackorder;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\Stock\BackorderService;
use AIArmada\Inventory\States\Fulfilled;
use AIArmada\Inventory\States\PartiallyFulfilled;
use AIArmada\Inventory\States\Pending;

describe('BackorderService', function (): void {
    beforeEach(function (): void {
        $this->service = new BackorderService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    });

    it('create backorder', function (): void {
        $backorder = $this->service->create(
            $this->item,
            10,
            $this->location->id,
            'order-123',
            'customer-456',
            BackorderPriority::High,
            now()->addDays(7),
            'Urgent order'
        );

        expect($backorder)->toBeInstanceOf(InventoryBackorder::class);
        expect($backorder->inventoryable_id)->toBe($this->item->id);
        expect($backorder->quantity_requested)->toBe(10);
        expect($backorder->status)->toBeInstanceOf(Pending::class);
        expect($backorder->priority)->toBe(BackorderPriority::High);
    });

    it('fulfill backorder', function (): void {
        $backorder = InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'status' => Pending::class,
        ]);

        $result = $this->service->fulfill($backorder, 5);

        expect($result)->toBeTrue();
        expect($backorder->fresh()->quantity_fulfilled)->toBe(5);
    });

    it('cancel backorder', function (): void {
        $backorder = InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'status' => Pending::class,
        ]);

        $result = $this->service->cancel($backorder, 5, 'Out of stock');

        expect($result)->toBeTrue();
        expect($backorder->fresh()->quantity_cancelled)->toBe(5);
    });

    it('get open backorders', function (): void {
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => Pending::class,
        ]);
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => PartiallyFulfilled::class,
        ]);
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => Fulfilled::class,
        ]);

        $openBackorders = $this->service->getOpenBackorders($this->item);

        expect($openBackorders)->toHaveCount(2);
    });

    it('get all open backorders', function (): void {
        InventoryBackorder::factory()->count(3)->create([
            'status' => Pending::class,
        ]);
        InventoryBackorder::factory()->create([
            'status' => Fulfilled::class,
        ]);

        $allOpen = $this->service->getAllOpenBackorders();

        expect($allOpen)->toHaveCount(3);
    });

    it('get overdue backorders', function (): void {
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'promised_at' => now()->subDay(),
        ]);
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'promised_at' => now()->addDays(7),
        ]);

        $overdue = $this->service->getOverdueBackorders();

        expect($overdue)->toHaveCount(1);
    });

    it('get backorders due within', function (): void {
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'promised_at' => now()->addDays(3),
        ]);
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'promised_at' => now()->addDays(10),
        ]);

        $dueWithin7Days = $this->service->getBackordersDueWithin(7);

        expect($dueWithin7Days)->toHaveCount(1);
    });

    it('auto fulfill', function (): void {
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 5,
            'quantity_fulfilled' => 0,
            'status' => Pending::class,
            'priority' => BackorderPriority::High,
        ]);
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
        ]);

        $result = $this->service->autoFulfill($this->item, 8, $this->location->id);

        expect($result['fulfilled'])->toBe(8);
        expect($result['backorders_updated'])->toBeGreaterThanOrEqual(1);
    });

    it('escalate overdue', function (): void {
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'promised_at' => now()->subDay(),
        ]);
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'priority' => BackorderPriority::Urgent,
            'promised_at' => now()->subDay(),
        ]);

        $escalated = $this->service->escalateOverdue();

        expect($escalated)->toBe(1);
    });

    it('expire old', function (): void {
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'requested_at' => now()->subDays(100),
        ]);
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'requested_at' => now()->subDays(10),
        ]);

        $expired = $this->service->expireOld(90);

        expect($expired)->toBe(1);
    });

    it('get statistics', function (): void {
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'priority' => BackorderPriority::High,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
        ]);
        InventoryBackorder::factory()->create([
            'status' => PartiallyFulfilled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 20,
            'quantity_fulfilled' => 5,
        ]);

        $stats = $this->service->getStatistics();

        expect($stats)->toHaveKeys(['total_open', 'total_quantity', 'overdue', 'by_priority']);
        expect($stats['total_open'])->toBe(2);
    });

    it('get fulfillable backorders', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 60,
            'quantity_reserved' => 10, // quantity_available = 50
        ]);

        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'status' => Pending::class,
        ]);

        $fulfillable = $this->service->getFulfillableBackorders();

        expect($fulfillable)->toHaveCount(1);
    });

    it('update promised date', function (): void {
        $backorder = InventoryBackorder::factory()->create([
            'promised_at' => now()->addDays(7),
        ]);

        $newDate = now()->addDays(14);
        $result = $this->service->updatePromisedDate($backorder, $newDate);

        expect($result)->toBeTrue();
        expect($backorder->fresh()->promised_at->toDateString())->toBe($newDate->toDateString());
    });

    it('get total backordered quantity', function (): void {
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity_requested' => 10,
            'quantity_fulfilled' => 2,
            'quantity_cancelled' => 0,
            'status' => Pending::class,
        ]);
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity_requested' => 20,
            'quantity_fulfilled' => 5,
            'quantity_cancelled' => 3,
            'status' => PartiallyFulfilled::class,
        ]);

        $total = $this->service->getTotalBackorderedQuantity($this->item);

        // (10-2-0) + (20-5-3) = 8 + 12 = 20
        expect($total)->toBe(20);
    });
});
