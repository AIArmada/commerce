<?php

declare(strict_types=1);

namespace AIArmada\Seating\Actions;

use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Models\SeatMap;
use Illuminate\Database\Eloquent\Model;

class ResolveSeatMapForHostAction
{
    public function handle(Model $host, SeatingMode $mode): ?SeatMap
    {
        if (! $mode->requiresAllocation()) {
            return null;
        }

        return SeatMap::forHost($host)->active()->first();
    }
}
