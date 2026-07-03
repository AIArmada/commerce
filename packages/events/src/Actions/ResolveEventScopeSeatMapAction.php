<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Ticketing\Models\Pass;

class ResolveEventScopeSeatMapAction
{
    public function handle(Pass $pass): ?SeatMap
    {
        if ($pass->session_id !== null) {
            $map = SeatMap::query()
                ->where('seatable_type', (new EventSession)->getMorphClass())
                ->where('seatable_id', $pass->session_id)
                ->active()
                ->first();

            if ($map !== null) {
                return $map;
            }
        }

        if ($pass->occurrence_id !== null) {
            $map = SeatMap::query()
                ->where('seatable_type', (new EventOccurrence)->getMorphClass())
                ->where('seatable_id', $pass->occurrence_id)
                ->active()
                ->first();

            if ($map !== null) {
                return $map;
            }
        }

        return SeatMap::forHost($pass->ticketable)->active()->first();
    }
}
