<?php

declare(strict_types=1);

use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Exceptions\SectionCapacityExceededException;
use AIArmada\Seating\Exceptions\StaleSeatHoldException;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Features\Maps\Models\SeatSection;
use AIArmada\Seating\Features\Reservations\Actions\ConvertHoldsToAllocationsAction;
use AIArmada\Seating\Features\Reservations\Actions\EnsureSectionAllocationAction;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use Carbon\CarbonImmutable;
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

it('does not convert a hold after its seat has been blocked', function (): void {
    $this->hold->seat->update(['status' => SeatStatus::Blocked]);

    expect(fn () => app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$this->hold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    ))->toThrow(StaleSeatHoldException::class);

    expect(SeatAllocation::count())->toBe(0)
        ->and($this->hold->fresh()->isConverted())->toBeFalse();
});

it('rejects conversion at the exact hold expiry timestamp', function (): void {
    $expiry = CarbonImmutable::now()->startOfSecond();
    $this->hold->update(['expires_at' => $expiry]);

    CarbonImmutable::setTestNow($expiry);

    try {
        expect(fn () => app(ConvertHoldsToAllocationsAction::class)->handle(
            holds: [$this->hold],
            mode: SeatingMode::Assigned,
            allocToType: 'pass',
            allocToId: (string) Str::orderedUuid(),
        ))->toThrow(StaleSeatHoldException::class);

        expect(SeatAllocation::count())->toBe(0)
            ->and($this->hold->fresh()->isConverted())->toBeFalse();
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('counts general admission allocations against assigned seat conversion capacity', function (): void {
    $section = $this->hold->seat->section;
    $section->update(['capacity' => 1]);

    app(EnsureSectionAllocationAction::class)->handle(
        section: $section,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    );

    expect(fn () => app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$this->hold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    ))->toThrow(SectionCapacityExceededException::class);

    expect(SeatAllocation::count())->toBe(1)
        ->and($this->hold->fresh()->isConverted())->toBeFalse();
});

it('rolls back a batch that would exceed section capacity', function (): void {
    $section = $this->hold->seat->section;
    $section->update(['capacity' => 1]);
    $secondSeat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);
    $secondHold = SeatHold::factory()->create(['seat_id' => $secondSeat->id]);

    expect(fn () => app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$this->hold, $secondHold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    ))->toThrow(SectionCapacityExceededException::class);

    expect(SeatAllocation::count())->toBe(0)
        ->and($this->hold->fresh()->isConverted())->toBeFalse()
        ->and($secondHold->fresh()->isConverted())->toBeFalse();
});
