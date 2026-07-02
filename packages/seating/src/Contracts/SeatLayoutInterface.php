<?php

declare(strict_types=1);

namespace AIArmada\Seating\Contracts;

use AIArmada\Seating\Models\SeatMap;

interface SeatLayoutInterface
{
    /** @return array<string, mixed> */
    public function describe(SeatMap $map): array;
}
