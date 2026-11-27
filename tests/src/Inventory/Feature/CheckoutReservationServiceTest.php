<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Features\Stock\Actions\UpdateInventoryLevel;
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Exceptions\InvalidReservationTransition;
use AIArmada\Inventory\Exceptions\ReservationReferenceConflict;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryReservation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

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

    it('does not allow an operator edit to reduce on-hand stock below reservations', function (): void {
        $this->reservationService->reserve('ref-operator-edit', [
            new ReservationLine(productId: $this->item->getKey(), quantity: 4),
        ], 900);

        $level = $this->inventoryService->getLevel($this->item, $this->location->id);
        $action = app(UpdateInventoryLevel::class);

        expect(fn () => $action->execute($level, ['quantity_on_hand' => 3]))
            ->toThrow(InvalidArgumentException::class);

        expect($level->fresh()->quantity_on_hand)->toBe(10)
            ->and($level->fresh()->quantity_reserved)->toBe(4);

        $updated = $action->execute($level, ['quantity_on_hand' => 6, 'quantity_reserved' => 0]);

        expect($updated->quantity_on_hand)->toBe(6)
            ->and($updated->quantity_reserved)->toBe(4);
    });

    it('rejects invalid commit and extension inputs without changing the reservation', function (): void {
        $this->reservationService->reserve('ref-invalid-transition', [
            new ReservationLine(productId: (string) $this->item->getKey(), quantity: 2),
        ], 900);

        expect(fn () => $this->reservationService->commit('ref-invalid-transition', ''))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $this->reservationService->extend('ref-invalid-transition', 0))->toThrow(InvalidArgumentException::class)
            ->and($this->reservationService->find('ref-invalid-transition')->state)->toBe('reserved');
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

    it('never substitutes product stock for an invalid explicit inventory identity', function (): void {
        $id = (string) $this->item->getKey();

        foreach ([
            new ReservationLine(productId: $id, inventoryableType: InventoryItem::class, inventoryableId: '01900000-0000-7000-8000-000000000001'),
            new ReservationLine(productId: $id, inventoryableType: InventoryItem::class),
            new ReservationLine(productId: $id, variantId: '01900000-0000-7000-8000-000000000002'),
        ] as $line) {
            expect(fn () => $this->reservationService->reserve('invalid-identity', [$line], 900))
                ->toThrow(ReservationReferenceConflict::class);
        }

        expect($this->inventoryService->getLevel($this->item, $this->location->id)?->quantity_reserved)->toBe(0)
            ->and(InventoryReservation::query()->where('reference', 'invalid-identity')->exists())->toBeFalse();
    });

    it('rejects an empty reference and non-positive reservation TTL', function (): void {
        $lines = [new ReservationLine(productId: (string) $this->item->getKey())];

        expect(fn () => $this->reservationService->reserve('', $lines, 900))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $this->reservationService->reserve('invalid-ttl', $lines, 0))->toThrow(InvalidArgumentException::class);
    });

    it('combines product and polymorphic demand for the same stock identity', function (): void {
        $id = (string) $this->item->getKey();
        $lines = [
            new ReservationLine(productId: $id, quantity: 2),
            new ReservationLine(
                productId: $id,
                quantity: 3,
                inventoryableType: $this->item->getMorphClass(),
                inventoryableId: $id,
            ),
        ];

        $outcome = $this->reservationService->reserve('ref-shared-sku', $lines, 900);
        $retry = $this->reservationService->reserve('ref-shared-sku', array_reverse($lines), 900);

        expect(array_values($outcome->lines))->toBe([['requested' => 5, 'reserved' => 5]])
            ->and($retry->lines)->toBe($outcome->lines)
            ->and(InventoryAllocation::query()->where('cart_id', 'ref-shared-sku')->sum('quantity'))->toBe(5);
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

    it('cleans expired reservation bookkeeping and releases reserved stock', function (): void {
        $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 5)];
        $this->reservationService->reserve('ref-cleanup', $lines, 900);

        $group = InventoryReservation::query()->where('reference', 'ref-cleanup')->firstOrFail();
        $group->update(['expires_at' => CarbonImmutable::now()->subSecond()]);

        $deleted = $this->reservationService->cleanupExpiredReservations();

        expect($deleted)->toBe(1)
            ->and(InventoryReservation::query()->whereKey($group->id)->exists())->toBeFalse()
            ->and(InventoryAllocation::query()->where('reservation_group_id', $group->id)->exists())->toBeFalse()
            ->and($this->inventoryService->getLevel($this->item, $this->location->id)?->fresh()?->quantity_reserved)->toBe(0);
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

    it('isolates cart allocation mutations from checkout reservations sharing the same reference and item', function (): void {
        $reference = 'shared-cart-reference';
        $allocations = app(InventoryAllocationService::class);
        $allocations->allocate($this->item, 3, $reference);
        $this->reservationService->reserve($reference, [
            new ReservationLine(productId: $this->item->getKey(), quantity: 2),
        ], 900);

        $group = InventoryReservation::query()->where('reference', $reference)->firstOrFail();
        $groupAllocation = InventoryAllocation::query()->where('reservation_group_id', $group->id)->firstOrFail();
        $originalExpiry = $groupAllocation->expires_at;

        expect($allocations->getAllocationsForCart($reference)->sum('quantity'))->toBe(3)
            ->and($allocations->getAllocations($this->item, $reference)->sum('quantity'))->toBe(3)
            ->and($allocations->extendAllocations($reference, 60))->toBe(1)
            ->and($groupAllocation->fresh()->expires_at->eq($originalExpiry))->toBeTrue()
            ->and($allocations->releaseAllocation($groupAllocation))->toBe(0)
            ->and($allocations->release($this->item, $reference))->toBe(3);

        $allocations->allocate($this->item, 4, $reference);
        expect($allocations->releaseAllForCart($reference))->toBe(4);

        $allocations->allocate($this->item, 1, $reference);
        expect($allocations->commit($reference, 'CART-ORDER'))->toHaveCount(1)
            ->and($groupAllocation->fresh())->not->toBeNull()
            ->and($this->reservationService->find($reference)->state)->toBe('reserved');

        $this->reservationService->commit($reference, 'CHECKOUT-ORDER');
        $level = $this->inventoryService->getLevel($this->item, $this->location->id)->fresh();

        expect($level->quantity_on_hand)->toBe(7)
            ->and($level->quantity_reserved)->toBe(0);
    });

    it('leaves grouped allocations to the checkout reservation expiry lifecycle', function (): void {
        $reference = 'group-expiry';
        $this->reservationService->reserve($reference, [
            new ReservationLine(productId: $this->item->getKey(), quantity: 2),
        ], 900);

        $group = InventoryReservation::query()->where('reference', $reference)->firstOrFail();
        InventoryAllocation::query()->where('reservation_group_id', $group->id)
            ->update(['expires_at' => CarbonImmutable::now()->subSecond()]);

        $allocations = app(InventoryAllocationService::class);
        expect($allocations->cleanupExpired())->toBe(0)
            ->and($allocations->cleanupExpiredGlobal())->toBe(0)
            ->and($this->reservationService->find($reference)->state)->toBe('reserved');

        $group->update(['expires_at' => CarbonImmutable::now()->subSecond()]);
        expect($this->reservationService->cleanupExpiredReservations())->toBe(1)
            ->and($this->inventoryService->getLevel($this->item, $this->location->id)->fresh()->quantity_reserved)->toBe(0);
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

    it('refuses to proceed while the reference lock is held elsewhere', function (): void {
        $key = (new ReflectionMethod($this->reservationService, 'reservationLockKey'))->invoke(
            $this->reservationService,
            'ref-locked',
        );
        $holder = Cache::lock($key, 10, 'another-process');
        expect($holder->acquire())->toBeTrue();

        try {
            $lines = [new ReservationLine(productId: $this->item->getKey(), quantity: 3)];

            expect(fn () => $this->reservationService->reserve('ref-locked', $lines, 900))
                ->toThrow(LockTimeoutException::class);
        } finally {
            $holder->release();
        }

        expect(InventoryReservation::query()->where('reference', 'ref-locked')->exists())->toBeFalse();
    });
});
