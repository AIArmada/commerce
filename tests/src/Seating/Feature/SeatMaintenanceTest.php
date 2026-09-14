<?php

declare(strict_types=1);

use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use AIArmada\Seating\Services\SeatLayoutRenderer;
use Illuminate\Support\Facades\DB;

it('clamps the release chunk size instead of failing', function (): void {
    $map = SeatMap::factory()->create();
    $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
    $seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);
    SeatHold::factory()->expired()->create(['seat_id' => $seat->id]);

    $this->artisan('seating:release-expired-holds', ['--chunk' => '0'])
        ->assertSuccessful();

    expect(SeatHold::query()->count())->toBe(0);

    SeatHold::factory()->expired()->create(['seat_id' => $seat->id]);

    $this->artisan('seating:release-expired-holds', ['--chunk' => 'not-a-number'])
        ->assertSuccessful();

    expect(SeatHold::query()->count())->toBe(0);

    SeatHold::factory()->expired()->create(['seat_id' => $seat->id]);

    $this->artisan('seating:release-expired-holds', ['--chunk' => '999999'])
        ->assertSuccessful();

    expect(SeatHold::query()->count())->toBe(0);
});

it('cascades map deletion through sections, seats, holds, and allocations', function (): void {
    $map = SeatMap::factory()->create();
    $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
    $seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);
    SeatHold::factory()->create(['seat_id' => $seat->id]);
    SeatAllocation::factory()->create(['seat_id' => $seat->id]);

    $map->delete();

    expect(SeatSection::query()->count())->toBe(0)
        ->and(Seat::query()->count())->toBe(0)
        ->and(SeatHold::query()->count())->toBe(0)
        ->and(SeatAllocation::query()->count())->toBe(0);
});

it('renders the layout with a single eager load per level', function (): void {
    $map = SeatMap::factory()->create();

    foreach (['A', 'B', 'C'] as $index => $code) {
        $section = SeatSection::factory()->create(['seat_map_id' => $map->id, 'code' => $code]);
        Seat::factory()->available()->create([
            'seat_section_id' => $section->id,
            'row_label' => $code,
            'row_number' => $index + 1,
            'column_number' => ($index + 1) * 2,
            'seat_label' => (string) ($index + 1),
        ]);
        Seat::factory()->available()->create([
            'seat_section_id' => $section->id,
            'row_label' => $code,
            'row_number' => 1,
            'column_number' => 1,
            'seat_label' => 'x' . $index,
        ]);
    }

    DB::enableQueryLog();

    $layout = app(SeatLayoutRenderer::class)->describe($map);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($layout['seats'])->toHaveCount(6)
        ->and($layout['bounds'])->toBe(['rows' => 3, 'cols' => 6])
        ->and($queries)->toHaveCount(2);
});
