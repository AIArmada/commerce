<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Queries;

use AIArmada\Seating\Contracts\SeatingMode;
use AIArmada\Seating\Data\AllocationResult;
use AIArmada\Seating\Features\Reservations\Actions\EnsureSeatHoldAction;
use AIArmada\Seating\Features\Reservations\Actions\ReleaseAllocationsAction;
use AIArmada\Seating\Features\Reservations\Api\ReservationsApi;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use AIArmada\Seating\Features\Reservations\Persistence\SeatReservationCleaner;
use AIArmada\Seating\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final readonly class EloquentReservationsApi implements ReservationsApi
{
    public function __construct(
        private EnsureSeatHoldAction $ensureSeatHold,
        private ReleaseAllocationsAction $releaseAllocations,
        private SeatReservationCleaner $cleaner,
    ) {}

    public function allocateHolds(
        string $mapId,
        int $quantity,
        SeatingMode $mode,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): array {
        $holds = $this->ensureSeatHold->handle(
            mapId: $mapId,
            quantity: $quantity,
            mode: $mode,
            heldByType: $heldByType,
            heldById: $heldById,
            reference: $reference,
            categoryPreferences: $categoryPreferences,
        );

        if ($holds->isEmpty()) {
            return [];
        }

        $seatClass = ModelResolver::seatClass();
        $seats = $seatClass::query()
            ->with('section')
            ->whereKey($holds->pluck('seat_id')->all())
            ->get()
            ->keyBy(fn (Model $seat): string => (string) $seat->getKey());

        $results = [];

        foreach ($holds as $hold) {
            $seat = $seats->get((string) $hold->seat_id);

            if (! $seat instanceof Model) {
                continue;
            }

            $section = $seat->getRelationValue('section');

            $results[] = new AllocationResult(
                seatId: (string) $seat->getKey(),
                sectionCode: $section instanceof Model ? (string) $section->getAttribute('code') : '',
                rowLabel: (string) $seat->getAttribute('row_label'),
                seatLabel: (string) $seat->getAttribute('seat_label'),
                category: $seat->getAttribute('category'),
                holdId: (string) $hold->getKey(),
            );
        }

        return $results;
    }

    public function releaseAllocations(string $allocToType, string $allocToId, ?string $reason = null): int
    {
        return $this->releaseAllocations->handle($allocToType, $allocToId, reason: $reason);
    }

    public function seatOccupancy(array $seatIds, CarbonImmutable $now): array
    {
        if ($seatIds === []) {
            return [];
        }

        $held = SeatHold::query()
            ->withoutOwnerScope()
            ->whereIn('seat_id', $seatIds)
            ->where('expires_at', '>', $now)
            ->whereNull('converted_at')
            ->pluck('seat_id')
            ->all();

        $sold = SeatAllocation::query()
            ->withoutOwnerScope()
            ->whereIn('seat_id', $seatIds)
            ->where('status', 'active')
            ->pluck('seat_id')
            ->all();

        $statuses = [];

        foreach ($sold as $seatId) {
            $statuses[(string) $seatId] = 'sold';
        }

        foreach ($held as $seatId) {
            $statuses[(string) $seatId] = 'held';
        }

        return $statuses;
    }

    public function isSeatOccupied(string $seatId, CarbonImmutable $now): bool
    {
        return $this->seatOccupancy([$seatId], $now) !== [];
    }

    public function deleteForSeat(string $seatId, ?string $ownerType, string | int | null $ownerId): int
    {
        return $this->cleaner->deleteForSeat($seatId, $ownerType, $ownerId);
    }

    public function deleteForSection(string $sectionId, ?string $ownerType, string | int | null $ownerId): int
    {
        return $this->cleaner->deleteForSection($sectionId, $ownerType, $ownerId);
    }
}
