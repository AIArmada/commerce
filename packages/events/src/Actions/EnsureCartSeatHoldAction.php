<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Seating\Actions\EnsureSeatHoldAction;
use AIArmada\Seating\Actions\ResolveSeatMapForHostAction;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EnsureCartSeatHoldAction
{
    public function __construct(
        private readonly EnsureSeatHoldAction $ensureSeatHold,
        private readonly ResolveSeatMapForHostAction $resolveSeatMap,
    ) {}

    /**
     * @return Collection<int, SeatHold>
     */
    public function handle(
        TicketType $ticketType,
        Model $scopeHost,
        int $quantity,
        ?string $heldByType = null,
        ?string $heldById = null,
    ): Collection {
        $seatingMode = $ticketType->seating_mode;

        if ($seatingMode === null || ! $seatingMode->requiresAllocation()) {
            return new Collection;
        }

        if ($seatingMode === SeatingMode::GeneralAdmission) {
            return new Collection;
        }

        $map = $this->resolveSeatMap->handle($scopeHost, $seatingMode);

        if ($map === null) {
            return new Collection;
        }

        return $this->ensureSeatHold->handle(
            map: $map,
            quantity: $quantity,
            mode: $seatingMode,
            heldByType: $heldByType,
            heldById: $heldById,
        );
    }
}
