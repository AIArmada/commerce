<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Exceptions\InconsistentSeatingModeException;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class EnsureEventScopeSeatingAction
{
    /**
     * @param  Collection<int, TicketType>  $ticketTypes
     */
    public function handle(Model $scopeTarget, Collection $ticketTypes): void
    {
        $requiresSeating = $ticketTypes->first(fn (TicketType $tt): bool => $tt->seating_mode?->requiresAllocation() ?? false);

        if ($requiresSeating === null) {
            return;
        }

        $hasMap = SeatMap::forHost($scopeTarget)->active()->exists();

        if (! $hasMap && $scopeTarget instanceof EventSession) {
            $occurrence = $scopeTarget->occurrence;

            if ($occurrence !== null) {
                $hasMap = SeatMap::forHost($occurrence)->active()->exists();
            }
        }

        if (! $hasMap && ($scopeTarget instanceof EventSession || $scopeTarget instanceof EventOccurrence)) {
            $event = $scopeTarget->event;

            if ($event !== null) {
                $hasMap = SeatMap::forHost($event)->active()->exists();
            }
        }

        if (! $hasMap) {
            throw new InconsistentSeatingModeException(
                'Seating mode is set but no active seat map exists for the event scope.'
            );
        }
    }
}
