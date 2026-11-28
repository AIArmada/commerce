<?php

declare(strict_types=1);

namespace AIArmada\Seating\Services;

use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Contracts\SeatingMode;

final readonly class NullSeatAllocator implements SeatAllocatorInterface
{
    public function allocate(
        string $mapId,
        int $quantity,
        SeatingMode $mode = SeatingMode::Assigned,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): array {
        return [];
    }
}
