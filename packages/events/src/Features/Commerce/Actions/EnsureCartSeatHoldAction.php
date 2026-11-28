<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventSeatingDriver;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;

final class EnsureCartSeatHoldAction
{
    public function __construct(
        private readonly EventSeatingDriver $seating,
    ) {}

    /**
     * @return list<string>
     */
    public function handle(
        string $ticketTypeId,
        string $scopeType,
        string $scopeId,
        int $quantity,
        ?string $heldByType = null,
        ?string $heldById = null,
    ): array {
        /** @var TicketType|null $ticketType */
        $ticketType = TicketType::query()->find($ticketTypeId);

        if (! $ticketType instanceof TicketType) {
            return [];
        }

        $seatingMode = $ticketType->seating_mode;

        if ($seatingMode === null
            || ! $seatingMode->requiresAllocation()
            || $seatingMode === SeatingMode::GeneralAdmission) {
            return [];
        }

        return $this->seating->holdSeats(
            scopeType: $scopeType,
            scopeId: $scopeId,
            seatingMode: $seatingMode->value,
            quantity: $quantity,
            heldByType: $heldByType,
            heldById: $heldById,
        );
    }
}
