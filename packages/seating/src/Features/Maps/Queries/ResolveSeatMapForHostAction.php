<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps\Queries;

use AIArmada\Seating\Contracts\SeatingMode;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use Illuminate\Database\Eloquent\Model;

final readonly class ResolveSeatMapForHostAction
{
    public function handle(Model $host, SeatingMode $mode): ?SeatMap
    {
        if (! $mode->requiresAllocation()) {
            return null;
        }

        return SeatMap::forHost($host)->active()->orderBy('created_at')->orderBy('id')->first();
    }
}
