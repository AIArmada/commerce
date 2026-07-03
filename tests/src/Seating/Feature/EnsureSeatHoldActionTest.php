<?php

declare(strict_types=1);

use AIArmada\Seating\Actions\EnsureSeatHoldAction;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;

beforeEach(function (): void {
    $this->map = SeatMap::factory()->create();
    $this->section = SeatSection::factory()->create([
        'seat_map_id' => $this->map->id,
        'code' => 'A',
    ]);

    for ($i = 1; $i <= 5; $i++) {
        Seat::factory()->available()->create([
            'seat_section_id' => $this->section->id,
            'row_label' => 'A',
            'row_number' => $i,
            'column_number' => 1,
            'seat_label' => (string) $i,
        ]);
    }
});

it('creates holds for assigned mode', function (): void {
    $holds = app(EnsureSeatHoldAction::class)->handle(
        map: $this->map,
        quantity: 3,
        mode: SeatingMode::Assigned,
    );

    expect($holds)->toHaveCount(3);
    expect(SeatHold::count())->toBe(3);
});

it('returns empty for None mode', function (): void {
    $holds = app(EnsureSeatHoldAction::class)->handle(
        map: $this->map,
        quantity: 3,
        mode: SeatingMode::None,
    );

    expect($holds)->toHaveCount(0);
    expect(SeatHold::count())->toBe(0);
});

it('throws when insufficient seats', function (): void {
    app(EnsureSeatHoldAction::class)->handle(
        map: $this->map,
        quantity: 10,
        mode: SeatingMode::Assigned,
    );
})->throws(InsufficientSeatsException::class);

it('returns empty for zero quantity', function (): void {
    $holds = app(EnsureSeatHoldAction::class)->handle(
        map: $this->map,
        quantity: 0,
        mode: SeatingMode::Assigned,
    );

    expect($holds)->toHaveCount(0);
});
