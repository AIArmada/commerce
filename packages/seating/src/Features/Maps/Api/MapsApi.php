<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps\Api;

use AIArmada\Seating\Data\SeatMapRef;
use AIArmada\Seating\Data\SeatSelectionData;

interface MapsApi
{
    public function resolveActiveMapId(?string $seatMapId, ?string $seatableType, ?string $seatableId): ?string;

    public function resolveMap(?string $seatMapId, ?string $seatableType, ?string $seatableId, bool $activeOnly = true): ?SeatMapRef;

    public function seatForSelection(string $seatId, string $mapId, ?string $sectionId = null): ?SeatSelectionData;

    /** @return array<string, string> Seat id to persisted seat status. */
    public function seatStatuses(string $mapId, ?string $sectionId = null): array;

    /** @return array<string, mixed> */
    public function layout(string $mapId, ?string $sectionId = null): array;
}
