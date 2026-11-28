<?php

declare(strict_types=1);

namespace AIArmada\Seating\Contracts;

use AIArmada\Seating\Data\AllocationResult;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;

interface SeatAllocatorInterface
{
    /**
     * @param  array<int, string>  $categoryPreferences
     * @return list<AllocationResult>
     *
     * @throws InsufficientSeatsException
     */
    public function allocate(
        string $mapId,
        int $quantity,
        SeatingMode $mode = SeatingMode::Assigned,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): array;
}
