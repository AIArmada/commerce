<?php

declare(strict_types=1);

namespace AIArmada\Seating\Events;

use AIArmada\Seating\Models\SeatAllocation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SeatAllocationReleased
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SeatAllocation $allocation,
    ) {}
}
