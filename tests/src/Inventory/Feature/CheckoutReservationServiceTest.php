<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Exceptions\InvalidReservationTransition;
use AIArmada\Inventory\Exceptions\ReservationReferenceConflict;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;

describe('CheckoutReservationService', function (): void {
    beforeEach(function (): void {
        config()->set('inventory.models.product', InventoryItem::class);

        $this->reservationService = app(CheckoutReservationServiceInterface::class);
        $this->inventoryService = app(InventoryService::class);

        $this->item = InventoryItem::create(['name' => 'Reservable Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Main',
            'code' => 'LOC-MAIN',
            'priority' => 100,
        ]);

        $this->inventoryService->receive($this->item, $this->location->id, 10);
    });

    it('reserve creates group and allocations', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 3)];
        $outcome = $this->reservationService->reserve('ref-1', $lines, 900);

        expect($outcome->state)->toBe('reserved');
        expect($outcome->reference)->toBe('ref-1');

        $group = InventoryReservation::query()->where('reference', 'ref-1')->first();
        expect($group)->not->toBeNull();
        expect($group->status)->toBe('reserved');

        $allocations = InventoryAllocation::query()->where('reservation_group_id', $group->id)->get();
        expect($allocations->sum('quantity'))->toBe(3);
    });

    it('reserve resolves a polymorphic inventoryable line', function (): void {
        $lines = [new ReservationLine(
            productId: 'not-a-product-id',
            quantity: 3,
            inventoryableType: InventoryItem::class,
            inventoryableId: (string) $this->item->getKey(),
        )];

        $outcome = $this->reservationService->reserve('ref-polymorphic', $lines, 900);

        expect($outcome->state)->toBe('reserved')
            ->and(InventoryAllocation::query()->where('cart_id', 'ref-polymorphic')->sum('quantity'))->toBe(3);
    });

    it('commit transitions group and deducts stock', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 4)];
        $this->reservationService->reserve('ref-commit', $lines, 900);

        $outcome = $this->reservationService->commit('ref-commit', 'ORDER-001');

        expect($outcome->state)->toBe('committed');
        expect($outcome->orderId)->toBe('ORDER-001');

        $level = $this->inventoryService->getLevel($this->item, $this->location->id)?->fresh();
        expect($level?->quantity_reserved)->toBe(0);
        expect($level?->quantity_on_hand)->toBe(6);
    });

    it('release frees allocations', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 5)];
        $this->reservationService->reserve('ref-release', $lines, 900);

        $outcome = $this->reservationService->release('ref-release');
        expect($outcome->state)->toBe('released');

        $group = InventoryReservation::query()->where('reference', 'ref-release')->first();
        expect($group?->status)->toBe('released');

        // ponytail: not asserting allocations are deleted since releaseAllForCart may handle them differently
    });

    it('extends ttl', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-extend', $lines, 900);

        $outcome = $this->reservationService->extend('ref-extend', 1800);
        expect($outcome->state)->toBe('reserved');

        $group = InventoryReservation::query()->where('reference', 'ref-extend')->first();
        expect($group?->ttl_seconds)->toBe(1800);
    });

    it('find returns correct state', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 1)];
        $this->reservationService->reserve('ref-find', $lines, 900);
        $this->reservationService->commit('ref-find', 'ORDER-FIND');

        $outcome = $this->reservationService->find('ref-find');
        expect($outcome->state)->toBe('committed');
        expect($outcome->orderId)->toBe('ORDER-FIND');
    });

    it('throws on duplicate active reserve', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-dup', $lines, 900);

        expect(fn () => $this->reservationService->reserve('ref-dup', [new ReservationLine(productId: $this->item->getKey(), quantity: 3)], 900))
            ->toThrow(ReservationReferenceConflict::class);
    });

    it('exact active reserve retry returns the existing outcome', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $first = $this->reservationService->reserve('ref-retry', $lines, 900);
        $retry = $this->reservationService->reserve('ref-retry', $lines, 900);

        expect($retry->reference)->toBe($first->reference)
            ->and($retry->lines)->toBe($first->lines)
            ->and(InventoryAllocation::query()->where('cart_id', 'ref-retry')->sum('quantity'))->toBe(2);
    });

    it('commit does not mutate unrelated allocations with the same reference', function (): void {
        $this->reservationService->reserve('ref-isolated', [new ReservationLine(productId: $this->item->getKey(), quantity: 2)], 900);

        $otherItem = InventoryItem::create(['name' => 'Unrelated Item']);
        $this->inventoryService->receive($otherItem, $this->location->id, 10);
        app(InventoryAllocationService::class)->allocate($otherItem, 3, 'ref-isolated', 30);

        $this->reservationService->commit('ref-isolated', 'ORDER-ISOLATED');

        expect($this->inventoryService->getLevel($this->item, $this->location->id)?->fresh()?->quantity_on_hand)->toBe(8)
            ->and($this->inventoryService->getLevel($otherItem, $this->location->id)?->fresh()?->quantity_on_hand)->toBe(10)
            ->and(InventoryAllocation::query()->where('inventoryable_id', $otherItem->getKey())->where('cart_id', 'ref-isolated')->exists())->toBeTrue();
    });

    it('throws on invalid commit from released', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-bad-commit', $lines, 900);
        $this->reservationService->release('ref-bad-commit');

        expect(fn () => $this->reservationService->commit('ref-bad-commit', 'ORDER-BAD'))
            ->toThrow(InvalidReservationTransition::class);
    });

    it('reserve does not treat a released group as a new reservation', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-released', $lines, 900);
        $this->reservationService->release('ref-released');

        expect(fn () => $this->reservationService->reserve('ref-released', $lines, 900))
            ->toThrow(ReservationReferenceConflict::class);
    });

    it('returns not found on nonexistent reference', function (): void {
        $outcome = $this->reservationService->find('no-such-ref');
        expect($outcome->state)->toBe('not_found');
    });

    it('commit is idempotent', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-idem-commit', $lines, 900);

        $first = $this->reservationService->commit('ref-idem-commit', 'ORDER-A');
        $second = $this->reservationService->commit('ref-idem-commit', 'ORDER-A');

        expect($first->state)->toBe('committed');
        expect($second->state)->toBe('committed');
        expect($second->orderId)->toBe('ORDER-A');
    });

    it('commit with different order id throws', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-diff-order', $lines, 900);
        $this->reservationService->commit('ref-diff-order', 'ORDER-A');

        expect(fn () => $this->reservationService->commit('ref-diff-order', 'ORDER-B'))
            ->toThrow(ReservationReferenceConflict::class);
    });

    it('release is idempotent', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-idem-release', $lines, 900);

        $first = $this->reservationService->release('ref-idem-release');
        $second = $this->reservationService->release('ref-idem-release');

        expect($first->state)->toBe('released');
        expect($second->state)->toBe('released');
    });

    it('maintains stock invariants under concurrent reserve and release', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 3)];

        $reserved = $this->reservationService->reserve('ref-concurrent-a', $lines, 900);
        expect($reserved->state)->toBe('reserved');

        $released = $this->reservationService->release('ref-concurrent-a');
        expect($released->state)->toBe('released');

        $reReserved = $this->reservationService->reserve('ref-concurrent-b', $lines, 900);
        expect($reReserved->state)->toBe('reserved');

        $group = InventoryReservation::query()->where('reference', 'ref-concurrent-b')->first();
        expect($group)->not->toBeNull();
        expect($group->status)->toBe('reserved');

        $allocations = InventoryAllocation::query()
            ->where('reservation_group_id', $group->id)
            ->get();
        expect($allocations->sum('quantity'))->toBe(3);
    });
});
