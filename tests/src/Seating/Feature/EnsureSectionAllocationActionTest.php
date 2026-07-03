<?php

declare(strict_types=1);

use AIArmada\Seating\Actions\EnsureSectionAllocationAction;
use AIArmada\Seating\Exceptions\SectionCapacityExceededException;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $map = SeatMap::factory()->create();
    $this->section = SeatSection::factory()->create([
        'seat_map_id' => $map->id,
        'capacity' => 2,
    ]);
});

it('creates allocation when under capacity', function (): void {
    $allocation = app(EnsureSectionAllocationAction::class)->handle(
        section: $this->section,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    );

    expect($allocation)->toBeInstanceOf(SeatAllocation::class);
    expect($allocation->seat_section_id)->toBe($this->section->id);
    expect($allocation->seat_id)->toBeNull();
});

it('throws when section is at capacity', function (): void {
    SeatAllocation::factory()->create([
        'seat_section_id' => $this->section->id,
        'state' => 'active',
    ]);
    SeatAllocation::factory()->create([
        'seat_section_id' => $this->section->id,
        'state' => 'active',
    ]);

    app(EnsureSectionAllocationAction::class)->handle(
        section: $this->section,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    );
})->throws(SectionCapacityExceededException::class);

it('ignores released allocations when counting capacity', function (): void {
    SeatAllocation::factory()->released()->create([
        'seat_section_id' => $this->section->id,
    ]);

    $allocation = app(EnsureSectionAllocationAction::class)->handle(
        section: $this->section,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    );

    expect($allocation)->toBeInstanceOf(SeatAllocation::class);
});
