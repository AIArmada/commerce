<?php

declare(strict_types=1);

namespace AIArmada\Seating\Services;

use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Contracts\SeatingMode;
use AIArmada\Seating\Features\Reservations\Api\ReservationsApi;

final readonly class DefaultSeatAllocator implements SeatAllocatorInterface
{
    public function __construct(private ReservationsApi $reservations) {}

    public function allocate(
        string $mapId,
        int $quantity,
        SeatingMode $mode = SeatingMode::Assigned,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): array {
        return $this->reservations->allocateHolds(
            mapId: $mapId,
            quantity: $quantity,
            mode: $mode,
            heldByType: $heldByType,
            heldById: $heldById,
            reference: $reference,
            categoryPreferences: $categoryPreferences,
        );
    }
}
