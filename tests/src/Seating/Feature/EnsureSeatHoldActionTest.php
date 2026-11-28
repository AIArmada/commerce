<?php

declare(strict_types=1);

use AIArmada\Seating\Contracts\SeatingMode;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Features\Maps\Models\SeatSection;
use AIArmada\Seating\Features\Reservations\Actions\EnsureSeatHoldAction;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;

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
        mapId: $this->map->id,
        quantity: 3,
        mode: SeatingMode::Assigned,
    );

    expect($holds)->toHaveCount(3);
    expect(SeatHold::count())->toBe(3);
});

it('returns empty for non-assigned modes', function (SeatingMode $mode): void {
    $holds = app(EnsureSeatHoldAction::class)->handle(
        mapId: $this->map->id,
        quantity: 3,
        mode: $mode,
    );

    expect($holds)->toHaveCount(0);
    expect(SeatHold::count())->toBe(0);
})->with([
    'none' => [SeatingMode::None],
    'general admission' => [SeatingMode::GeneralAdmission],
]);

it('throws when insufficient seats', function (): void {
    expect(fn (): mixed => app(EnsureSeatHoldAction::class)->handle(
        mapId: $this->map->id,
        quantity: 10,
        mode: SeatingMode::Assigned,
    ))->toThrow(InsufficientSeatsException::class);

    expect(SeatHold::count())->toBe(0);
});

it('returns empty for zero quantity', function (): void {
    $holds = app(EnsureSeatHoldAction::class)->handle(
        mapId: $this->map->id,
        quantity: 0,
        mode: SeatingMode::Assigned,
    );

    expect($holds)->toHaveCount(0);
});
