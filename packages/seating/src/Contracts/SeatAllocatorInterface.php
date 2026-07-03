<?php

declare(strict_types=1);

namespace AIArmada\Seating\Contracts;

use AIArmada\Seating\Data\AllocationResult;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Models\SeatMap;
use Illuminate\Support\Collection;

interface SeatAllocatorInterface
{
    /**
     * @param  array<int, string>  $categoryPreferences
     * @return Collection<int, AllocationResult>
     *
     * @throws InsufficientSeatsException
     */
    public function allocate(
        SeatMap $map,
        int $quantity,
        SeatingMode $mode = SeatingMode::Assigned,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): Collection;
}
