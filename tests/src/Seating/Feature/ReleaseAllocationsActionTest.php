<?php

declare(strict_types=1);

use AIArmada\Seating\Actions\ReleaseAllocationsAction;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $map = SeatMap::factory()->create();
    $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
    $seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);
    $this->allocToType = 'pass';
    $this->allocToId = (string) Str::orderedUuid();
    SeatAllocation::factory()->create([
        'seat_id' => $seat->id,
        'allocated_to_type' => $this->allocToType,
        'allocated_to_id' => $this->allocToId,
    ]);
});

it('releases active allocations', function (): void {
    $count = app(ReleaseAllocationsAction::class)->handle(
        allocToType: $this->allocToType,
        allocToId: $this->allocToId,
    );

    expect($count)->toBe(1);
    $allocation = SeatAllocation::query()->first();
    expect($allocation->state)->toBe('released');
    expect($allocation->released_at)->not->toBeNull();
});

it('returns zero when no active allocations', function (): void {
    SeatAllocation::query()->update(['state' => 'released', 'released_at' => now()]);

    $count = app(ReleaseAllocationsAction::class)->handle(
        allocToType: $this->allocToType,
        allocToId: $this->allocToId,
    );

    expect($count)->toBe(0);
});

it('does not release revoked allocations', function (): void {
    SeatAllocation::query()->update(['state' => 'revoked', 'revoked_at' => now()]);

    $count = app(ReleaseAllocationsAction::class)->handle(
        allocToType: $this->allocToType,
        allocToId: $this->allocToId,
    );

    expect($count)->toBe(0);
});
