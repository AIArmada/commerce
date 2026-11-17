<?php

declare(strict_types=1);

use AIArmada\Inventory\Contracts\ScopedCapacityReservationServiceInterface;
use AIArmada\Inventory\Data\ScopedCapacityLine;
use AIArmada\Inventory\Models\CapacityHold;
use AIArmada\Inventory\Models\CapacityPool;
use Carbon\CarbonImmutable;

it('atomically holds pooled scoped capacity and enforces reference idempotency', function (): void {
    $service = app(ScopedCapacityReservationServiceInterface::class);
    $service->setCapacity('room-1', 'friday', 5);
    $service->setCapacity('room-1', 'saturday', 8);

    $friday = new ScopedCapacityLine('room-1', 'friday', 3);
    $saturday = new ScopedCapacityLine('room-1', 'saturday', 4);

    $first = $service->reserve('booking-1', [$friday, $saturday], 900);

    expect($first->state)->toBe('held')
        ->and($service->available('room-1', 'friday'))->toBe(2)
        ->and($service->available('room-1', 'saturday'))->toBe(4)
        ->and($service->reserve('booking-1', [$saturday, $friday], 900)->lines)->toBe($first->lines)
        ->and(CapacityHold::query()->where('reference', 'booking-1')->count())->toBe(2);

    expect(fn () => $service->reserve('booking-2', [
        new ScopedCapacityLine('room-1', 'friday', 3),
    ], 900))->toThrow(RuntimeException::class, 'Insufficient');
});

it('releases holds on checkout compensation but never releases committed capacity', function (): void {
    $service = app(ScopedCapacityReservationServiceInterface::class);
    $service->setCapacity('show', 'evening', 3);

    $service->reserve('checkout-1', [new ScopedCapacityLine('show', 'evening', 2)], 120);
    expect($service->available('show', 'evening'))->toBe(1);

    $released = $service->release('checkout-1');
    expect($released->state)->toBe('released')
        ->and($service->release('checkout-1')->state)->toBe('released')
        ->and($service->available('show', 'evening'))->toBe(3);

    $service->reserve('checkout-2', [new ScopedCapacityLine('show', 'evening', 2)], 120);
    expect($service->commit('checkout-2', 'order-1')->state)->toBe('committed')
        ->and($service->commit('checkout-2', 'order-1')->orderId)->toBe('order-1')
        ->and($service->available('show', 'evening'))->toBe(1);

    expect(fn () => $service->release('checkout-2'))->toThrow(RuntimeException::class, 'Committed');
});

it('expires holds and returns capacity to the same pool without affecting another window', function (): void {
    $service = app(ScopedCapacityReservationServiceInterface::class);
    $service->setCapacity('seat', 'session-a', 4);
    $service->setCapacity('seat', 'session-b', 4);

    CarbonImmutable::setTestNow('2026-12-01 10:00:00');

    try {
        $service->reserve('temp-1', [new ScopedCapacityLine('seat', 'session-a', 3)], 60);
        $service->reserve('temp-2', [new ScopedCapacityLine('seat', 'session-b', 2)], 3600);

        CarbonImmutable::setTestNow('2026-12-01 10:02:00');
        expect($service->available('seat', 'session-a'))->toBe(4)
            ->and($service->available('seat', 'session-b'))->toBe(2)
            ->and($service->find('temp-1')->state)->toBe('expired')
            ->and($service->find('temp-2')->state)->toBe('held');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('cannot reduce capacity below committed demand or change a terminal reference', function (): void {
    $service = app(ScopedCapacityReservationServiceInterface::class);
    $service->setCapacity('asset', 'scope-1', 5);
    $service->reserve('fixed-ref', [new ScopedCapacityLine('asset', 'scope-1', 4)], 900);
    $service->commit('fixed-ref', 'order-123');

    expect(fn () => $service->setCapacity('asset', 'scope-1', 3))
        ->toThrow(RuntimeException::class, 'below reserved and committed')
        ->and(fn () => $service->reserve('fixed-ref', [new ScopedCapacityLine('asset', 'scope-1', 4)], 900))
        ->toThrow(RuntimeException::class, 'terminal');

    expect(CapacityPool::query()->firstOrFail()->committed)->toBe(4);
});
