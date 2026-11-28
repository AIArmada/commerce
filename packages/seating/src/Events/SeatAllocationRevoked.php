<?php

declare(strict_types=1);

namespace AIArmada\Seating\Events;

use AIArmada\Seating\Data\SeatAllocationData;
use Illuminate\Foundation\Events\Dispatchable;

final class SeatAllocationRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly SeatAllocationData $allocation,
    ) {}
}
