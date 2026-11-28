<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Api;

use AIArmada\Seating\Contracts\SeatingMode;
use AIArmada\Seating\Data\AllocationResult;
use Carbon\CarbonImmutable;

interface ReservationsApi
{
    /**
     * @param  array<int, string>  $categoryPreferences
     * @return list<AllocationResult>
     */
    public function allocateHolds(
        string $mapId,
        int $quantity,
        SeatingMode $mode,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): array;

    public function releaseAllocations(string $allocToType, string $allocToId, ?string $reason = null): int;

    /** @param array<int, string> $seatIds */
    /** @return array<string, string> Seat id to held/sold status. */
    public function seatOccupancy(array $seatIds, CarbonImmutable $now): array;

    public function isSeatOccupied(string $seatId, CarbonImmutable $now): bool;

    public function deleteForSeat(string $seatId, ?string $ownerType, string | int | null $ownerId): int;

    public function deleteForSection(string $sectionId, ?string $ownerType, string | int | null $ownerId): int;
}
