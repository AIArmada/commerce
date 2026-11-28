<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAdjusted;
use AIArmada\Inventory\Events\InventoryReceived;
use AIArmada\Inventory\Events\InventoryShipped;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Stock\Persistence\ReservationStockStore;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\Event;

describe('InventoryService', function (): void {
    beforeEach(function (): void {
        $this->inventoryService = app(InventoryService::class);
        $this->item = InventoryItem::create(['name' => 'Test Inventory Item']);
        $this->locationA = InventoryLocation::factory()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'priority' => 100,
        ]);
        $this->locationB = InventoryLocation::factory()->create([
            'name' => 'Overflow Warehouse',
            'code' => 'OVER',
            'priority' => 50,
        ]);
    });

    it('does not create default locations for unsuccessful default stock operations', function (): void {
        InventoryLocation::query()->where('code', InventoryLocation::DEFAULT_LOCATION_CODE)->delete();

        expect(fn () => $this->inventoryService->receiveAtDefault($this->item, 0))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn () => $this->inventoryService->shipFromDefault($this->item, 1))
            ->toThrow(InsufficientInventoryException::class)
            ->and(InventoryLocation::query()->where('code', InventoryLocation::DEFAULT_LOCATION_CODE)->exists())->toBeFalse();
    });

    it('receives and ships through the canonical default-location actions', function (): void {
        $receipt = $this->inventoryService->receiveAtDefault($this->item, 4);
        $shipment = $this->inventoryService->shipFromDefault($this->item, 1);

        expect($receipt->to_location_id)->toBe($shipment->from_location_id)
            ->and($this->inventoryService->getTotalAvailable($this->item))->toBe(3);
    });

    it('receives inventory and records movement', function (): void {
        Event::fake();

        $movement = $this->inventoryService->receive($this->item, $this->locationA->id, 15, 'restock', 'batch-1', 'user-1');
        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);

        expect($level)->not->toBeNull();
        expect($level->quantity_on_hand)->toBe(15);
        expect($movement->type)->toBe(MovementType::Receipt->value);

        Event::assertDispatched(InventoryReceived::class);
    });

    it('ships inventory and dispatches low or out of inventory events when depleted', function (): void {
        Event::fake();

        $this->inventoryService->receive($this->item, $this->locationA->id, 3);
        $movement = $this->inventoryService->ship($this->item, $this->locationA->id, 3, 'sale', 'ORDER-1');
        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);

        expect($movement->type)->toBe(MovementType::Shipment->value);
        expect($level?->quantity_on_hand)->toBe(0);

        Event::assertDispatched(InventoryShipped::class);
        Event::assertDispatched(OutOfInventory::class);
    });

    it('throws when shipping more than available', function (): void {
        $this->inventoryService->receive($this->item, $this->locationA->id, 2);

        $this->inventoryService->ship($this->item, $this->locationA->id, 3);
    })->throws(InsufficientInventoryException::class);

    it('shipping considers reserved quantity', function (): void {
        $this->inventoryService->receive($this->item, $this->locationA->id, 20);

        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);
        expect($level)->not->toBeNull();

        // Reserved stock is ledger-managed; the fixture bypasses mass
        // assignment like the allocation service does internally.
        $level->forceFill(['quantity_reserved' => 15])->save();

        $this->inventoryService->ship($this->item, $this->locationA->id, 10);
    })->throws(InsufficientInventoryException::class);

    it('transfers inventory between locations and updates levels', function (): void {
        Event::fake();

        $this->inventoryService->receive($this->item, $this->locationA->id, 5);
        $this->inventoryService->transfer($this->item, $this->locationA->id, $this->locationB->id, 2, 'restock-b');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id);
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id);

        expect($levelA?->quantity_on_hand)->toBe(3);
        expect($levelB?->quantity_on_hand)->toBe(2);

        Event::assertDispatched(InventoryTransferred::class);
        Event::assertNotDispatched(InventoryShipped::class);
    });

    it('adjusts inventory to a target quantity', function (): void {
        Event::fake();

        $this->inventoryService->receive($this->item, $this->locationA->id, 10);
        $movement = $this->inventoryService->adjust($this->item, $this->locationA->id, 4, 'cycle_count');

        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);

        expect($movement->type)->toBe(MovementType::Adjustment->value);
        expect($level?->quantity_on_hand)->toBe(4);

        Event::assertDispatched(InventoryAdjusted::class);
        Event::assertDispatched(LowInventoryDetected::class);
    });

    it('reports aggregated availability across locations', function (): void {
        $this->inventoryService->receive($this->item, $this->locationA->id, 7);
        $this->inventoryService->receive($this->item, $this->locationB->id, 5);

        $availability = $this->inventoryService->getAvailability($this->item);

        expect($availability)->toMatchArray([
            $this->locationA->id => 7,
            $this->locationB->id => 5,
        ]);
        expect($this->inventoryService->hasInventory($this->item, 10))->toBeTrue();
        expect($this->inventoryService->hasInventory($this->item, 20))->toBeFalse();
    });

    it('excludes inactive locations consistently from aggregate stock queries', function (): void {
        $this->inventoryService->receive($this->item, $this->locationA->id, 7);
        $this->inventoryService->receive($this->item, $this->locationB->id, 5);
        $this->locationB->update(['is_active' => false]);

        $key = $this->item->getMorphClass() . ':' . $this->item->getKey();

        expect($this->inventoryService->getAvailability($this->item))->toBe([$this->locationA->id => 7])
            ->and($this->inventoryService->getTotalAvailable($this->item))->toBe(7)
            ->and($this->inventoryService->getTotalOnHand($this->item))->toBe(7)
            ->and($this->inventoryService->getAvailabilityForMany([$this->item]))->toBe([$key => 7]);
    });

    it('reports bulk availability for many models in one call', function (): void {
        $other = InventoryItem::create(['name' => 'Other Inventory Item']);
        $unstocked = InventoryItem::create(['name' => 'Unstocked Inventory Item']);

        $this->inventoryService->receive($this->item, $this->locationA->id, 7);
        $this->inventoryService->receive($this->item, $this->locationB->id, 5);
        $this->inventoryService->receive($other, $this->locationA->id, 3);

        $totals = $this->inventoryService->getAvailabilityForMany([$this->item, $other, $unstocked]);

        expect($totals)->toMatchArray([
            $this->item->getMorphClass() . ':' . $this->item->getKey() => 12,
            $other->getMorphClass() . ':' . $other->getKey() => 3,
            $unstocked->getMorphClass() . ':' . $unstocked->getKey() => 0,
        ]);
    });

    it('bounds bulk stock queries and deduplicates identities across batches', function (): void {
        $this->inventoryService->receive($this->item, $this->locationA->id, 7);

        $models = [$this->item];

        for ($i = 0; $i < 201; $i++) {
            $models[] = InventoryItem::create(['name' => 'Bulk item ' . $i]);
        }

        $last = $models[array_key_last($models)];
        $this->inventoryService->receive($last, $this->locationB->id, 3);
        $models[] = $this->item;

        $totals = $this->inventoryService->getAvailabilityForMany($models);

        expect($totals)->toHaveCount(202)
            ->and($totals[$this->item->getMorphClass() . ':' . $this->item->getKey()])->toBe(7)
            ->and($totals[$last->getMorphClass() . ':' . $last->getKey()])->toBe(3)
            ->and(array_sum($totals))->toBe(10);
    });

    it('records exact before and after balances without duplicate ledger movements', function (): void {
        $first = $this->inventoryService->receive($this->item, $this->locationA->id, 10);
        $second = $this->inventoryService->receive($this->item, $this->locationA->id, 5);
        $shipment = $this->inventoryService->ship($this->item, $this->locationA->id, 4);
        $adjustment = $this->inventoryService->adjust($this->item, $this->locationA->id, 8);

        expect([$first->quantity_before, $first->quantity_after])->toBe([0, 10])
            ->and([$second->quantity_before, $second->quantity_after])->toBe([10, 15])
            ->and([$shipment->quantity_before, $shipment->quantity_after])->toBe([15, 11])
            ->and([$adjustment->quantity_before, $adjustment->quantity_after])->toBe([11, 8])
            ->and($this->inventoryService->getTotalOnHand($this->item))->toBe(8)
            ->and(InventoryMovement::query()
                ->where('inventoryable_type', $this->item->getMorphClass())
                ->where('inventoryable_id', $this->item->getKey())
                ->count())->toBe(4);
    });

    it('returns an empty map for an empty bulk availability request', function (): void {
        expect($this->inventoryService->getAvailabilityForMany([]))->toBe([]);
    });
});

it('rolls back stock and reservation operations when a balance write is cancelled', function (): void {
    $item = InventoryItem::create(['name' => 'Cancelled Ledger Item']);
    $source = InventoryLocation::factory()->create();
    $destination = InventoryLocation::factory()->create();
    $inventory = app(InventoryService::class);
    $inventory->receive($item, $source->id, 100);
    $inventory->receive($item, $destination->id, 10);
    $level = $inventory->getLevel($item, $source->id);
    $stock = app(ReservationStockStore::class);
    $stock->reserve($level->id, 20);
    $movements = InventoryMovement::query()->count();

    InventoryLevel::updating(static fn (InventoryLevel $record): bool => $record->getKey() !== $level->getKey());

    foreach ([
        fn () => $inventory->receive($item, $source->id, 5),
        fn () => $inventory->ship($item, $source->id, 5),
        fn () => $inventory->adjust($item, $source->id, 95),
        fn () => $inventory->transfer($item, $source->id, $destination->id, 5),
        fn () => $stock->reserve($level->id, 5),
        fn () => $stock->release($level->id, 5),
        fn () => $stock->commit($level->id, 5, null, 'Cancelled commit'),
    ] as $operation) {
        expect($operation)->toThrow(RuntimeException::class, 'cancelled');
    }

    expect($level->fresh()->quantity_on_hand)->toBe(100)
        ->and($level->fresh()->quantity_reserved)->toBe(20)
        ->and($inventory->getLevel($item, $destination->id)->quantity_on_hand)->toBe(10)
        ->and(InventoryMovement::query()->count())->toBe($movements);
});

it('rolls back stock when movement creation is cancelled', function (): void {
    $item = InventoryItem::create(['name' => 'Cancelled Movement Item']);
    $location = InventoryLocation::factory()->create();
    $inventory = app(InventoryService::class);
    $inventory->receive($item, $location->id, 100);
    $movements = InventoryMovement::query()->count();

    InventoryMovement::creating(static fn (InventoryMovement $movement): bool => $movement->inventoryable_id !== $item->getKey());

    expect(fn () => $inventory->receive($item, $location->id, 5))
        ->toThrow(RuntimeException::class, 'movement creation was cancelled')
        ->and($inventory->getTotalOnHand($item))->toBe(100)
        ->and(InventoryMovement::query()->count())->toBe($movements);
});
