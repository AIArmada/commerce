<?php

declare(strict_types=1);

use AIArmada\Seating\Livewire\SeatMap;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatMap as SeatMapModel;
use AIArmada\Seating\Models\SeatSection;

beforeEach(function () {
    $this->map = SeatMapModel::factory()->create();
    $section = SeatSection::factory()->create([
        'seat_map_id' => $this->map->id,
        'code' => 'A',
    ]);
    Seat::factory()->available()->create([
        'seat_section_id' => $section->id,
        'row_label' => 'A',
        'row_number' => 1,
        'column_number' => 1,
        'seat_label' => '1',
    ]);
});

it('renders the seat map', function () {
    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id);

    expect($component->render())->not->toBeNull();
});

it('allows picking available seats', function () {
    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id, selectable: true);

    $component->toggleSeat(Seat::first()->id);

    expect($component->picked)->toBe([Seat::first()->id]);
});

it('deselects on second click', function () {
    $seatId = Seat::first()->id;

    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id, selectable: true);
    $component->toggleSeat($seatId);
    $component->toggleSeat($seatId);

    expect($component->picked)->toBe([]);
});

it('clears selection', function () {
    $seatId = Seat::first()->id;

    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id, selectable: true);
    $component->toggleSeat($seatId);
    $component->clearSelection();

    expect($component->picked)->toBe([]);
});

it('disables selection when not selectable', function () {
    $seatId = Seat::first()->id;

    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id, selectable: false);
    $component->toggleSeat($seatId);

    expect($component->picked)->toBe([]);
});

it('does not allow picking held seats', function () {
    $seat = Seat::first();
    $seat->holds()->create(['expires_at' => now()->addMinutes(5)]);
    $seatId = $seat->id;

    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id);
    $component->toggleSeat($seatId);

    expect($component->picked)->toBe([]);
});

it('does not allow picking blocked seats', function () {
    $seat = Seat::first();
    $seat->update(['status' => 'blocked']);
    $seatId = $seat->id;

    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id);
    $component->toggleSeat($seatId);

    expect($component->picked)->toBe([]);
});

it('does not allow picking seats from another map', function (): void {
    $otherMap = SeatMapModel::factory()->create();
    $otherSection = SeatSection::factory()->create([
        'seat_map_id' => $otherMap->id,
        'code' => 'B',
    ]);
    $otherSeat = Seat::factory()->available()->create([
        'seat_section_id' => $otherSection->id,
        'row_label' => 'B',
        'row_number' => 1,
        'column_number' => 1,
        'seat_label' => '1',
    ]);

    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id, selectable: true);
    $component->toggleSeat($otherSeat->id);

    expect($component->picked)->toBe([]);
});

it('shows legend when showLegend is true', function () {
    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id, showLegend: true);

    expect($component->showLegend)->toBeTrue();
});

it('hides legend when showLegend is false', function () {
    $component = new SeatMap;
    $component->mount(seatMapId: $this->map->id, showLegend: false);

    expect($component->showLegend)->toBeFalse();
});
