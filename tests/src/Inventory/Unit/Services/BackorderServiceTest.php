<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\BackorderPriority;
use AIArmada\Inventory\Features\Backorders\Models\InventoryBackorder;
use AIArmada\Inventory\Features\Backorders\Persistence\BackorderService;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\States\Fulfilled;
use AIArmada\Inventory\States\PartiallyFulfilled;
use AIArmada\Inventory\States\Pending;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

describe('BackorderService', function (): void {
    beforeEach(function (): void {
        $this->service = new BackorderService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    });

    it('rejects invalid backorder quantities', function (): void {
        expect(fn () => $this->service->create($this->item, 0, $this->location->id))->toThrow(InvalidArgumentException::class);
        $backorder = $this->service->create($this->item, 10, $this->location->id);
        expect(fn () => $this->service->fulfill($backorder, -1))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $this->service->cancel($backorder, 0))->toThrow(InvalidArgumentException::class)
            ->and($backorder->fresh()->quantityRemaining())->toBe(10);
    });

    it('uses current persisted quantities when fulfilling a stale backorder', function (): void {
        $backorder = $this->service->create($this->item, 10, $this->location->id);
        $stale = $backorder->fresh();

        expect($this->service->fulfill($backorder, 8))->toBeTrue()
            ->and($this->service->fulfill($stale, 8))->toBeTrue()
            ->and($backorder->fresh()->quantity_fulfilled)->toBe(10);
    });

    it('create backorder', function (): void {
        $backorder = $this->service->create(
            $this->item,
            10,
            $this->location->id,
            'order-123',
            'customer-456',
            BackorderPriority::High,
            CarbonImmutable::now()->addDays(7),
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
            'promised_at' => CarbonImmutable::now()->subDay(),
        ]);
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'promised_at' => CarbonImmutable::now()->addDays(7),
        ]);

        $overdue = $this->service->getOverdueBackorders();

        expect($overdue)->toHaveCount(1);
    });

    it('get backorders due within', function (): void {
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'promised_at' => CarbonImmutable::now()->addDays(3),
        ]);
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'promised_at' => CarbonImmutable::now()->addDays(10),
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
            'promised_at' => CarbonImmutable::now()->subDay(),
        ]);
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'priority' => BackorderPriority::Urgent,
            'promised_at' => CarbonImmutable::now()->subDay(),
        ]);

        $escalated = $this->service->escalateOverdue();

        expect($escalated)->toBe(1);
    });

    it('expire old', function (): void {
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'requested_at' => CarbonImmutable::now()->subDays(100),
        ]);
        InventoryBackorder::factory()->create([
            'status' => Pending::class,
            'requested_at' => CarbonImmutable::now()->subDays(10),
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
            'quantity_reserved' => 10, // available = 50
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

    it('does not subtract an over-reserved location from sellable stock elsewhere', function (): void {
        $otherLocation = InventoryLocation::factory()->create(['is_active' => true]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
        ]);
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $otherLocation->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 15,
        ]);

        $backorder = InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => null,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'status' => Pending::class,
        ]);

        expect($this->service->getFulfillableBackorders()->modelKeys())->toContain($backorder->getKey());
    });

    it('update promised date', function (): void {
        $backorder = InventoryBackorder::factory()->create([
            'promised_at' => CarbonImmutable::now()->addDays(7),
        ]);

        $newDate = CarbonImmutable::now()->addDays(14);
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

it('batches backorder availability by item and excludes inactive locations', function (): void {
    config()->set('inventory.owner.enabled', false);

    $service = new BackorderService;
    $item = InventoryItem::create(['name' => 'Batched Backorder Item']);
    $other = InventoryItem::create(['name' => 'Unstocked Backorder Item']);
    $active = InventoryLocation::factory()->create(['is_active' => true]);
    $inactive = InventoryLocation::factory()->create(['is_active' => false]);

    foreach ([[$active, 10], [$inactive, 100]] as [$location, $quantity]) {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => 0,
        ]);
    }

    $expected = [];

    foreach (range(1, 20) as $index) {
        $expected[] = $service->create($item, 5, $active->id)->id;
    }

    $expected[] = $service->create($item, 10)->id;
    $service->create($item, 11);
    $service->create($item, 1, $inactive->id);
    $service->create($other, 1);

    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $actual = $service->getFulfillableBackorders()->modelKeys();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($actual)->toEqualCanonicalizing($expected)
        ->and(count($queries))->toBeLessThanOrEqual(3);
});
