<?php

declare(strict_types=1);

namespace AIArmada\Seating\Services;

use AIArmada\Seating\Features\Reservations\Actions\EnsureSeatHoldAction;
use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Data\AllocationResult;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use Illuminate\Support\Collection;

final readonly class DefaultSeatAllocator implements SeatAllocatorInterface
{
    public function __construct(private EnsureSeatHoldAction $ensureHold) {}

    public function allocate(
        SeatMap $map,
        int $quantity,
        SeatingMode $mode = SeatingMode::Assigned,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): Collection {
        return $this->ensureHold->handle(
            $map,
            $quantity,
            $mode,
            $heldByType,
            $heldById,
            $reference,
            $categoryPreferences,
        )->map(static function (SeatHold $hold): AllocationResult {
            $seat = $hold->seat;

            return new AllocationResult(
                seatId: $seat->id,
                sectionCode: $seat->section?->code ?? '',
                rowLabel: $seat->row_label,
                seatLabel: $seat->seat_label,
                category: $seat->category,
                holdId: $hold->id,
            );
        });
    }
}
