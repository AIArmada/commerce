<?php

declare(strict_types=1);

use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Services\NullSeatAllocator;

it('returns empty collection', function (): void {
    $map = SeatMap::factory()->create();
    $allocator = new NullSeatAllocator;

    $results = $allocator->allocate($map, quantity: 5);

    expect($results)->toHaveCount(0);
});
