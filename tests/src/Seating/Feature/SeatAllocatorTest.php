<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use AIArmada\Seating\Services\DefaultSeatAllocator;

beforeEach(function (): void {
    $this->map = SeatMap::factory()->create();
    $this->section = SeatSection::factory()->create([
        'seat_map_id' => $this->map->id,
        'code' => 'A',
    ]);

    for ($i = 1; $i <= 15; $i++) {
        Seat::factory()->available()->create([
            'seat_section_id' => $this->section->id,
            'row_label' => (string) chr(64 + $i),
            'row_number' => $i,
            'column_number' => 1,
            'seat_label' => (string) $i,
            'category' => 'standard',
        ]);
    }

    app()->bind(SeatAllocatorInterface::class, DefaultSeatAllocator::class);
});

it('allocates requested quantity of seats', function (): void {
    $results = app(SeatAllocatorInterface::class)->allocate(
        map: $this->map,
        quantity: 3,
    );

    expect($results)->toHaveCount(3);
    expect($results->pluck('sectionCode')->all())->each->toBe('A');
});

it('throws when insufficient seats', function (): void {
    app(SeatAllocatorInterface::class)->allocate(
        map: $this->map,
        quantity: 20,
    );
})->throws(InsufficientSeatsException::class);

it('creates holds for allocated seats', function (): void {
    app(SeatAllocatorInterface::class)->allocate(
        map: $this->map,
        quantity: 2,
    );

    expect(SeatHold::count())->toBe(2);
});

it('stores the held by morph relation', function (): void {
    $holder = User::query()->create([
        'name' => 'Seat Holder',
        'email' => 'seat-holder@example.com',
        'password' => 'secret',
    ]);

    $results = app(SeatAllocatorInterface::class)->allocate(
        map: $this->map,
        quantity: 1,
        heldByType: $holder->getMorphClass(),
        heldById: $holder->id,
    );

    $hold = SeatHold::query()->findOrFail($results->first()->holdId);

    expect($hold->heldBy?->is($holder))->toBeTrue();
});

it('skips held seats', function (): void {
    $seat = Seat::firstOrFail();
    SeatHold::factory()->create(['seat_id' => $seat->id]);

    if (config('seating.holds.ttl_minutes', 15) > 0) {
        $results = app(SeatAllocatorInterface::class)->allocate(
            map: $this->map,
            quantity: 10,
        );

        expect($results)->toHaveCount(10);
        expect($results->pluck('seatId'))->not->toContain($seat->id);
    }
});

it('skips blocked seats', function (): void {
    Seat::query()->update(['status' => 'available']);
    $seat = Seat::firstOrFail();
    $seat->update(['status' => 'blocked']);

    $results = app(SeatAllocatorInterface::class)->allocate(
        map: $this->map,
        quantity: 10,
    );

    expect($results)->toHaveCount(10);
    expect($results->pluck('seatId'))->not->toContain($seat->id);
});

it('respects category preferences', function (): void {
    Seat::query()->update(['category' => 'standard']);

    Seat::factory()->available()->create([
        'seat_section_id' => $this->section->id,
        'category' => 'vip',
        'row_label' => 'Z',
        'row_number' => 50,
        'column_number' => 1,
        'seat_label' => 'VIP1',
    ]);

    $results = app(SeatAllocatorInterface::class)->allocate(
        map: $this->map,
        quantity: 1,
        categoryPreferences: ['vip'],
    );

    expect($results)->toHaveCount(1);
    expect($results->first()->category)->toBe('vip');
});

it('returns empty collection for zero quantity', function (): void {
    $results = app(SeatAllocatorInterface::class)->allocate(
        map: $this->map,
        quantity: 0,
    );

    expect($results)->toHaveCount(0);
});
