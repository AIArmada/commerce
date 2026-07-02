<?php

declare(strict_types=1);

namespace AIArmada\Seating\Services;

use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Models\SeatMap;
use Illuminate\Support\Collection;

final class NullSeatAllocator implements SeatAllocatorInterface
{
    public function allocate(
        SeatMap $map,
        int $quantity,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): Collection {
        return new Collection;
    }
}
