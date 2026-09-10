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
    expect($allocation->status)->toBe('released');
    expect($allocation->released_at)->not->toBeNull();
});

it('releases every active allocation in a batch', function (): void {
    SeatAllocation::factory()->count(2)->create([
        'allocated_to_type' => $this->allocToType,
        'allocated_to_id' => $this->allocToId,
    ]);

    $count = app(ReleaseAllocationsAction::class)->handle(
        allocToType: $this->allocToType,
        allocToId: $this->allocToId,
    );

    expect($count)->toBe(3)
        ->and(SeatAllocation::query()->where('status', 'active')->count())->toBe(0)
        ->and(SeatAllocation::query()->where('status', 'released')->count())->toBe(3);
});

it('returns zero when no active allocations', function (): void {
    SeatAllocation::query()->update(['status' => 'released', 'released_at' => now()]);

    $count = app(ReleaseAllocationsAction::class)->handle(
        allocToType: $this->allocToType,
        allocToId: $this->allocToId,
    );

    expect($count)->toBe(0);
});

it('does not release revoked allocations', function (): void {
    SeatAllocation::query()->update(['status' => 'revoked', 'revoked_at' => now()]);

    $count = app(ReleaseAllocationsAction::class)->handle(
        allocToType: $this->allocToType,
        allocToId: $this->allocToId,
    );

    expect($count)->toBe(0);
});
