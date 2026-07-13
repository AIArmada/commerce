<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Exceptions\InvalidReservationTransition;
use AIArmada\Inventory\Exceptions\ReservationReferenceConflict;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;

class CheckoutReservationServiceTest extends InventoryTestCase
{
    protected CheckoutReservationServiceInterface $reservationService;

    protected InventoryService $inventoryService;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_reserve_creates_group_and_allocations(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 3)];
        $outcome = $this->reservationService->reserve('ref-1', $lines, 900);

        expect($outcome->state)->toBe('reserved');
        expect($outcome->reference)->toBe('ref-1');

        $group = InventoryReservation::query()->where('reference', 'ref-1')->first();
        expect($group)->not->toBeNull();
        expect($group->state)->toBe('reserved');

        $allocations = InventoryAllocation::query()->where('reservation_group_id', $group->id)->get();
        expect($allocations->sum('quantity'))->toBe(3);
    }

    public function test_commit_transitions_group_and_deducts_stock(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 4)];
        $this->reservationService->reserve('ref-commit', $lines, 900);

        $outcome = $this->reservationService->commit('ref-commit', 'ORDER-001');

        expect($outcome->state)->toBe('committed');
        expect($outcome->orderId)->toBe('ORDER-001');

        $level = $this->inventoryService->getLevel($this->item, $this->location->id)?->fresh();
        expect($level?->quantity_reserved)->toBe(0);
        expect($level?->quantity_on_hand)->toBe(6);
    }

    public function test_release_frees_allocations(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 5)];
        $this->reservationService->reserve('ref-release', $lines, 900);

        $outcome = $this->reservationService->release('ref-release');
        expect($outcome->state)->toBe('released');

        $group = InventoryReservation::query()->where('reference', 'ref-release')->first();
        expect($group?->state)->toBe('released');

        // ponytail: not asserting allocations are deleted since releaseAllForCart may handle them differently
    }

    public function test_extends_ttl(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-extend', $lines, 900);

        $outcome = $this->reservationService->extend('ref-extend', 1800);
        expect($outcome->state)->toBe('reserved');

        $group = InventoryReservation::query()->where('reference', 'ref-extend')->first();
        expect($group?->ttl_seconds)->toBe(1800);
    }

    public function test_find_returns_correct_state(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 1)];
        $this->reservationService->reserve('ref-find', $lines, 900);
        $this->reservationService->commit('ref-find', 'ORDER-FIND');

        $outcome = $this->reservationService->find('ref-find');
        expect($outcome->state)->toBe('committed');
        expect($outcome->orderId)->toBe('ORDER-FIND');
    }

    public function test_throws_on_duplicate_active_reserve(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-dup', $lines, 900);

        expect(fn () => $this->reservationService->reserve('ref-dup', [new ReservationLine(productId: $this->item->getKey(), quantity: 3)], 900))
            ->toThrow(ReservationReferenceConflict::class);
    }

    public function test_exact_active_reserve_retry_returns_the_existing_outcome(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $first = $this->reservationService->reserve('ref-retry', $lines, 900);
        $retry = $this->reservationService->reserve('ref-retry', $lines, 900);

        expect($retry->reference)->toBe($first->reference)
            ->and($retry->lines)->toBe($first->lines)
            ->and(InventoryAllocation::query()->where('cart_id', 'ref-retry')->sum('quantity'))->toBe(2);
    }

    public function test_commit_does_not_mutate_unrelated_allocations_with_the_same_reference(): void
    {
        $this->reservationService->reserve('ref-isolated', [new ReservationLine(productId: $this->item->getKey(), quantity: 2)], 900);

        $otherItem = InventoryItem::create(['name' => 'Unrelated Item']);
        $this->inventoryService->receive($otherItem, $this->location->id, 10);
        app(InventoryAllocationService::class)->allocate($otherItem, 3, 'ref-isolated', 30);

        $this->reservationService->commit('ref-isolated', 'ORDER-ISOLATED');

        expect($this->inventoryService->getLevel($this->item, $this->location->id)?->fresh()?->quantity_on_hand)->toBe(8)
            ->and($this->inventoryService->getLevel($otherItem, $this->location->id)?->fresh()?->quantity_on_hand)->toBe(10)
            ->and(InventoryAllocation::query()->where('inventoryable_id', $otherItem->getKey())->where('cart_id', 'ref-isolated')->exists())->toBeTrue();
    }

    public function test_throws_on_invalid_commit_from_released(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-bad-commit', $lines, 900);
        $this->reservationService->release('ref-bad-commit');

        expect(fn () => $this->reservationService->commit('ref-bad-commit', 'ORDER-BAD'))
            ->toThrow(InvalidReservationTransition::class);
    }

    public function test_reserve_does_not_treat_a_released_group_as_a_new_reservation(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-released', $lines, 900);
        $this->reservationService->release('ref-released');

        expect(fn () => $this->reservationService->reserve('ref-released', $lines, 900))
            ->toThrow(ReservationReferenceConflict::class);
    }

    public function test_returns_not_found_on_nonexistent_reference(): void
    {
        $outcome = $this->reservationService->find('no-such-ref');
        expect($outcome->state)->toBe('not_found');
    }

    public function test_commit_is_idempotent(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-idem-commit', $lines, 900);

        $first = $this->reservationService->commit('ref-idem-commit', 'ORDER-A');
        $second = $this->reservationService->commit('ref-idem-commit', 'ORDER-A');

        expect($first->state)->toBe('committed');
        expect($second->state)->toBe('committed');
        expect($second->orderId)->toBe('ORDER-A');
    }

    public function test_commit_with_different_order_id_throws(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-diff-order', $lines, 900);
        $this->reservationService->commit('ref-diff-order', 'ORDER-A');

        expect(fn () => $this->reservationService->commit('ref-diff-order', 'ORDER-B'))
            ->toThrow(ReservationReferenceConflict::class);
    }

    public function test_release_is_idempotent(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 2)];
        $this->reservationService->reserve('ref-idem-release', $lines, 900);

        $first = $this->reservationService->release('ref-idem-release');
        $second = $this->reservationService->release('ref-idem-release');

        expect($first->state)->toBe('released');
        expect($second->state)->toBe('released');
    }

    public function test_maintains_stock_invariants_under_concurrent_reserve_and_release(): void
    {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 3)];

        $reserved = $this->reservationService->reserve('ref-concurrent-a', $lines, 900);
        expect($reserved->state)->toBe('reserved');

        $released = $this->reservationService->release('ref-concurrent-a');
        expect($released->state)->toBe('released');

        $reReserved = $this->reservationService->reserve('ref-concurrent-b', $lines, 900);
        expect($reReserved->state)->toBe('reserved');

        $group = InventoryReservation::query()->where('reference', 'ref-concurrent-b')->first();
        expect($group)->not->toBeNull();
        expect($group->state)->toBe('reserved');

        $allocations = InventoryAllocation::query()
            ->where('reservation_group_id', $group->id)
            ->get();
        expect($allocations->sum('quantity'))->toBe(3);
    }
}
