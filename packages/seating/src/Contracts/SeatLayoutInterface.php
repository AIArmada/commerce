<?php

declare(strict_types=1);

namespace AIArmada\Seating\Contracts;

interface SeatLayoutInterface
{
    /** @return array<string, mixed> */
    public function describe(string $mapId, ?string $sectionId = null): array;
}
