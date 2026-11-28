<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Contracts\SeatingMode;
use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Features\Maps\Models\SeatSection;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
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
        mapId: $this->map->id,
        quantity: 3,
    );

    expect($results)->toHaveCount(3);
    expect(collect($results)->pluck('sectionCode')->all())->each->toBe('A');
});

it('throws when insufficient seats', function (): void {
    expect(fn (): mixed => app(SeatAllocatorInterface::class)->allocate(
        mapId: $this->map->id,
        quantity: 20,
    ))->toThrow(InsufficientSeatsException::class);

    expect(SeatHold::count())->toBe(0);
});

it('does not create holds for general admission mode', function (): void {
    $results = app(SeatAllocatorInterface::class)->allocate(
        mapId: $this->map->id,
        quantity: 3,
        mode: SeatingMode::GeneralAdmission,
    );

    expect($results)->toHaveCount(0)
        ->and(SeatHold::count())->toBe(0);
});

it('creates holds for allocated seats', function (): void {
    app(SeatAllocatorInterface::class)->allocate(
        mapId: $this->map->id,
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
        mapId: $this->map->id,
        quantity: 1,
        heldByType: $holder->getMorphClass(),
        heldById: $holder->id,
    );

    $hold = SeatHold::query()->findOrFail($results[0]->holdId);

    expect($hold->heldBy?->is($holder))->toBeTrue();
});

it('skips held seats', function (): void {
    config()->set('seating.holds.ttl_minutes', 15);

    $seat = Seat::firstOrFail();
    SeatHold::factory()->create(['seat_id' => $seat->id]);

    $results = app(SeatAllocatorInterface::class)->allocate(
        mapId: $this->map->id,
        quantity: 10,
    );

    expect($results)->toHaveCount(10);
    expect(collect($results)->pluck('seatId'))->not->toContain($seat->id);
});

it('skips blocked seats', function (): void {
    Seat::query()->update(['status' => SeatStatus::Available]);
    $seat = Seat::firstOrFail();
    $seat->update(['status' => SeatStatus::Blocked]);

    $results = app(SeatAllocatorInterface::class)->allocate(
        mapId: $this->map->id,
        quantity: 10,
    );

    expect($results)->toHaveCount(10);
    expect(collect($results)->pluck('seatId'))->not->toContain($seat->id);
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
        mapId: $this->map->id,
        quantity: 1,
        categoryPreferences: ['vip'],
    );

    expect($results)->toHaveCount(1);
    expect($results[0]->category)->toBe('vip');
});

it('returns an empty list for zero quantity', function (): void {
    $results = app(SeatAllocatorInterface::class)->allocate(
        mapId: $this->map->id,
        quantity: 0,
    );

    expect($results)->toHaveCount(0);
});

it('never holds a seat with an active allocation even when the seat status is available', function (): void {
    $seat = Seat::query()->orderBy('row_number')->firstOrFail();
    SeatAllocation::factory()->create(['seat_id' => $seat->id, 'status' => 'active']);

    $results = app(SeatAllocatorInterface::class)->allocate(mapId: $this->map->id, quantity: 14);

    expect($results)->toHaveCount(14)
        ->and(collect($results)->pluck('seatId')->all())->not->toContain($seat->id);
});

it('can hold a seat again after its converted allocation has been released', function (): void {
    $seat = Seat::query()->orderBy('row_number')->firstOrFail();
    SeatHold::factory()->converted()->create(['seat_id' => $seat->id]);

    $results = app(SeatAllocatorInterface::class)->allocate(mapId: $this->map->id, quantity: 15);

    expect($results)->toHaveCount(15)
        ->and(collect($results)->pluck('seatId')->all())->toContain($seat->id);
});
