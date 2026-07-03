<?php

declare(strict_types=1);

use AIArmada\Seating\Actions\ConvertHoldsToAllocationsAction;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $map = SeatMap::factory()->create();
    $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
    $seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);
    $this->hold = SeatHold::factory()->create(['seat_id' => $seat->id]);
});

it('converts holds to allocations', function (): void {
    $allocations = app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$this->hold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    );

    expect($allocations)->toHaveCount(1);
    expect(SeatAllocation::count())->toBe(1);
    expect($this->hold->fresh()->isConverted())->toBeTrue();
});

it('skips already converted holds', function (): void {
    $this->hold->markConverted();

    $allocations = app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$this->hold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    );

    expect($allocations)->toHaveCount(0);
});

it('denormalizes seat_section_id on allocation', function (): void {
    $allocations = app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$this->hold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    );

    $allocation = $allocations->first();
    expect($allocation->seat_section_id)->not->toBeNull();
});
