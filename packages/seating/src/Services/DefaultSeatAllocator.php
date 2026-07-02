<?php

declare(strict_types=1);

namespace AIArmada\Seating\Services;

use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Data\AllocationResult;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DefaultSeatAllocator implements SeatAllocatorInterface
{
    public function allocate(
        SeatMap $map,
        int $quantity,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): Collection {
        if ($quantity <= 0) {
            return new Collection;
        }

        return DB::transaction(function () use ($map, $quantity, $heldByType, $heldById, $reference, $categoryPreferences): Collection {
            $ttlMinutes = (int) config('seating.holds.ttl_minutes', 15);
            $expiresAt = now()->addMinutes($ttlMinutes);

            $allocated = new Collection;

            for ($i = 0; $i < $quantity; $i++) {
                $seat = $this->pickSeat($map, $categoryPreferences, $allocated);

                if ($seat === null) {
                    throw new InsufficientSeatsException(
                        "Could not allocate {$quantity} seats; only " . $allocated->count() . ' available.'
                    );
                }

                $hold = SeatHold::query()->create([
                    'seat_id' => $seat->id,
                    'held_by_type' => $heldByType,
                    'held_by_id' => $heldById,
                    'reference' => $reference,
                    'expires_at' => $expiresAt,
                ]);

                $allocated->push(new AllocationResult(
                    seatId: $seat->id,
                    sectionCode: $seat->section?->code ?? '',
                    rowLabel: $seat->row_label,
                    seatLabel: $seat->seat_label,
                    category: $seat->category,
                    holdId: $hold->id,
                ));
            }

            return $allocated;
        });
    }

    private function pickSeat(SeatMap $map, array $preferences, Collection $already): ?Seat
    {
        $query = $this->availableSeatsQuery($map, $already);

        if ($preferences !== []) {
            $query->whereIn('category', $preferences);
            $seat = $query->first();

            if ($seat !== null) {
                return $seat;
            }

            $query = $this->availableSeatsQuery($map, $already);
        }

        return $query->first();
    }

    /** @return Builder<Seat> */
    private function availableSeatsQuery(SeatMap $map, Collection $already): Builder
    {
        return Seat::query()
            ->whereHas('section', fn (Builder $query): Builder => $query->where('seat_map_id', $map->id))
            ->where('status', 'available')
            ->whereNotIn('id', $already->pluck('seatId')->all())
            ->whereDoesntHave('holds', fn (Builder $query): Builder => $query->where('expires_at', '>', now()))
            ->orderBy('seat_section_id')
            ->orderBy('row_number')
            ->orderBy('column_number')
            ->lockForUpdate();
    }
}
