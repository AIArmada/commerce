<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Livewire\SeatMap;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatMap as SeatMapModel;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->map = SeatMapModel::factory()->create();
    $this->sectionA = SeatSection::factory()->create([
        'seat_map_id' => $this->map->id,
        'code' => 'A',
        'sort_order' => 1,
    ]);
    $this->sectionB = SeatSection::factory()->create([
        'seat_map_id' => $this->map->id,
        'code' => 'B',
        'sort_order' => 2,
    ]);

    $this->seatA = Seat::factory()->available()->create([
        'seat_section_id' => $this->sectionA->id,
        'row_label' => 'A',
        'row_number' => 1,
        'column_number' => 1,
        'seat_label' => '1',
    ]);
    $this->seatB = Seat::factory()->available()->create([
        'seat_section_id' => $this->sectionB->id,
        'row_label' => 'B',
        'row_number' => 1,
        'column_number' => 1,
        'seat_label' => '1',
    ]);
});

function makeSeatMapComponent(string $mapId, ?string $sectionId = null): SeatMap
{
    $component = new SeatMap;
    $component->mount(seatMapId: $mapId, sectionId: $sectionId);

    return $component;
}

it('caps how many seats can be picked', function (): void {
    $component = makeSeatMapComponent((string) $this->map->id);
    $component->picked = array_fill(0, SeatMap::MAX_SELECTION, (string) $this->seatA->id);

    $component->toggleSeat((string) $this->seatB->id);

    expect($component->picked)->toHaveCount(SeatMap::MAX_SELECTION);
});

it('still deselects a seat when the selection is full', function (): void {
    $component = makeSeatMapComponent((string) $this->map->id);
    $component->picked = array_fill(0, SeatMap::MAX_SELECTION - 1, 'other-seat');
    $component->toggleSeat((string) $this->seatA->id);

    expect($component->picked)->toHaveCount(SeatMap::MAX_SELECTION);

    $component->toggleSeat((string) $this->seatA->id);

    expect($component->picked)->toHaveCount(SeatMap::MAX_SELECTION - 1);
});

it('scopes layout and status to one section when requested', function (): void {
    $component = makeSeatMapComponent((string) $this->map->id, (string) $this->sectionA->id);

    $layout = $component->getLayoutProperty();
    $status = $component->getStatusProperty();

    expect($layout['sections'])->toHaveCount(1)
        ->and($layout['sections'][0]['code'])->toBe('A')
        ->and($status)->toHaveCount(1)
        ->and(array_key_exists((string) $this->seatA->id, $status))->toBeTrue();
});

it('caches the layout per map version', function (): void {
    Cache::flush();

    $first = makeSeatMapComponent((string) $this->map->id)->getLayoutProperty();

    Seat::query()->whereKey($this->seatB->id)->delete();

    $cached = makeSeatMapComponent((string) $this->map->id)->getLayoutProperty();

    expect($cached)->toBe($first);

    $this->map->forceFill(['version' => $this->map->version + 1])->save();

    $fresh = makeSeatMapComponent((string) $this->map->id)->getLayoutProperty();

    expect($fresh['seats'])->toHaveCount(count($first['seats']) - 1);
});

it('reports blocked and picked seats', function (): void {
    $this->seatB->forceFill(['status' => SeatStatus::Blocked])->save();

    $component = makeSeatMapComponent((string) $this->map->id);
    $component->toggleSeat((string) $this->seatA->id);

    $status = $component->getStatusProperty();

    expect($status[(string) $this->seatA->id])->toBe(SeatStatus::Picked->value)
        ->and($status[(string) $this->seatB->id])->toBe(SeatStatus::Blocked->value);
});

it('ignores seatable types that do not resolve to a model', function (): void {
    $component = new SeatMap;
    $component->mount(
        seatableType: 'not-a-model-' . uniqid(),
        seatableId: (string) $this->map->id,
    );

    expect($component->getLayoutProperty()['map'])->toBeNull()
        ->and($component->getStatusProperty())->toBe([]);
});

it('resolves maps through a real seatable model class', function (): void {
    $owner = User::query()->create([
        'name' => 'Seatable Owner',
        'email' => 'seatable-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $this->map->forceFill([
        'seatable_type' => User::class,
        'seatable_id' => $owner->id,
    ])->save();

    $component = new SeatMap;
    $component->mount(
        seatableType: User::class,
        seatableId: (string) $owner->id,
    );

    expect($component->getLayoutProperty()['map']['id'])->toBe((string) $this->map->id);
});

it('stops toggling once the per-minute budget is spent', function (): void {
    $component = makeSeatMapComponent((string) $this->map->id);

    for ($i = 0; $i < SeatMap::TOGGLE_RATE_LIMIT + 5; $i++) {
        $component->toggleSeat((string) $this->seatA->id);
    }

    // 60 effective toggles is even, so the seat ends deselected; without a
    // limit the 65 attempts would end with the seat selected.
    expect($component->picked)->toBe([]);
});
