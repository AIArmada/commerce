<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventSeatingDriver;
use AIArmada\Events\Exceptions\InconsistentSeatingModeException;
use AIArmada\Ticketing\Models\TicketType;

final class EnsureEventScopeSeatingAction
{
    public function __construct(
        private readonly EventSeatingDriver $seating,
    ) {}

    /**
     * @param  list<string>  $ticketTypeIds
     */
    public function handle(string $scopeType, string $scopeId, array $ticketTypeIds): void
    {
        if ($ticketTypeIds === []) {
            return;
        }

        $requiresSeating = TicketType::query()
            ->whereKey($ticketTypeIds)
            ->get()
            ->first(static fn (TicketType $ticketType): bool => $ticketType->seating_mode?->requiresAllocation() ?? false);

        if (! $requiresSeating instanceof TicketType) {
            return;
        }

        if (! $this->seating->hasActiveSeatMap($scopeType, $scopeId)) {
            throw new InconsistentSeatingModeException(
                'Seating mode is set but no active seat map exists for the event scope.',
            );
        }
    }
}
