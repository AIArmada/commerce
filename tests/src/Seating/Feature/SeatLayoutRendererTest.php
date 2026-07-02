<?php

declare(strict_types=1);

use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use AIArmada\Seating\Services\SeatLayoutRenderer;

it('describes seat map bounds from rendered seats', function (): void {
    $map = SeatMap::factory()->create();
    $section = SeatSection::factory()->create([
        'seat_map_id' => $map->id,
        'code' => 'A',
        'sort_order' => 1,
    ]);

    Seat::factory()->available()->create([
        'seat_section_id' => $section->id,
        'row_label' => 'A',
        'row_number' => 1,
        'column_number' => 1,
        'seat_label' => '1',
    ]);
    Seat::factory()->available()->create([
        'seat_section_id' => $section->id,
        'row_label' => 'B',
        'row_number' => 2,
        'column_number' => 3,
        'seat_label' => '3',
    ]);

    $layout = app(SeatLayoutRenderer::class)->describe($map);

    expect($layout['bounds'])->toBe(['rows' => 2, 'cols' => 3])
        ->and($layout['seats'])->toHaveCount(2);
});
