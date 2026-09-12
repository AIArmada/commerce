<?php

declare(strict_types=1);

use AIArmada\Seating\Actions\ConvertHoldsToAllocationsAction;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

it('does not create duplicate active allocations for competing holds', function (): void {
    $competingHold = SeatHold::factory()->create(['seat_id' => $this->hold->seat_id]);

    $allocations = app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$this->hold, $competingHold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    );

    expect($allocations)->toHaveCount(1)
        ->and(SeatAllocation::query()->where('seat_id', $this->hold->seat_id)->where('status', 'active')->count())->toBe(1)
        ->and(SeatHold::query()->whereNotNull('converted_at')->count())->toBe(1);
});

it('enforces one active allocation per seat at the database boundary', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
        $this->markTestSkipped('The partial unique index is only available on PostgreSQL and SQLite.');
    }

    SeatAllocation::factory()->create(['seat_id' => $this->hold->seat_id]);

    expect(fn (): SeatAllocation => DB::transaction(
        fn (): SeatAllocation => SeatAllocation::factory()->create(['seat_id' => $this->hold->seat_id]),
    ))->toThrow(QueryException::class);
});
