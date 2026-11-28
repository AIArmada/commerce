<?php

declare(strict_types=1);

use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Services\NullSeatAllocator;

it('returns an empty list', function (): void {
    $map = SeatMap::factory()->create();
    $allocator = new NullSeatAllocator;

    $results = $allocator->allocate($map->id, quantity: 5);

    expect($results)->toHaveCount(0);
});
