<?php

declare(strict_types=1);

use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Models\Seat;

it('defines the formal seat status vocabulary', function (): void {
    expect(SeatStatus::cases())->toHaveCount(5)
        ->and(array_map(fn (SeatStatus $status): string => $status->value, SeatStatus::cases()))->toBe([
            'available',
            'held',
            'sold',
            'picked',
            'blocked',
        ]);
});

it('casts persisted seat status values to the formal enum', function (): void {
    $seat = new Seat;

    expect($seat->status)->toBe(SeatStatus::Available)
        ->and($seat->getCasts()['status'])->toBe(SeatStatus::class);
});
