<?php

declare(strict_types=1);

use AIArmada\Seating\Actions\ConvertHoldsToAllocationsAction;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $map = SeatMap::factory()->create();
    $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
    $this->seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);
});

it('round-trips integer-like holder ids as strings on holds', function (): void {
    $hold = SeatHold::factory()->create([
        'seat_id' => $this->seat->id,
        'held_by_type' => 'user',
        'held_by_id' => '42',
    ]);

    expect($hold->fresh()->held_by_id)->toBe('42');
});

it('round-trips uuid holder ids on holds', function (): void {
    $uuid = (string) Str::orderedUuid();

    $hold = SeatHold::factory()->create([
        'seat_id' => $this->seat->id,
        'held_by_type' => 'pass',
        'held_by_id' => $uuid,
    ]);

    expect($hold->fresh()->held_by_id)->toBe($uuid);
});

it('round-trips integer-like holder ids as strings on allocations', function (): void {
    $hold = SeatHold::factory()->create(['seat_id' => $this->seat->id]);

    $allocations = app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$hold],
        mode: SeatingMode::Assigned,
        allocToType: 'user',
        allocToId: '42',
    );

    expect($allocations->first()->fresh()->allocated_to_id)->toBe('42');
});

it('resolves a seat map for an integer-keyed host model', function (): void {
    $host = new class extends Model
    {
        public $incrementing = true;

        protected $keyType = 'int';

        protected $table = 'users';
    };
    $host->id = 42;

    $map = SeatMap::factory()->create([
        'seatable_type' => $host->getMorphClass(),
        'seatable_id' => '42',
    ]);

    expect(SeatMap::forHost($host)->first()?->getKey())->toBe($map->getKey());
});

it('resolves a seat map for a uuid-keyed host model', function (): void {
    $host = new class extends Model
    {
        public $incrementing = false;

        protected $keyType = 'string';

        protected $table = 'events';
    };
    $host->id = (string) Str::orderedUuid();

    $map = SeatMap::factory()->create([
        'seatable_type' => $host->getMorphClass(),
        'seatable_id' => $host->getKey(),
    ]);

    expect(SeatMap::forHost($host)->first()?->getKey())->toBe($map->getKey());
});
