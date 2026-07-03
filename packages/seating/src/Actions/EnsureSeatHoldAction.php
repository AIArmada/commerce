<?php

declare(strict_types=1);

namespace AIArmada\Seating\Actions;

use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnsureSeatHoldAction
{
    /**
     * @param  array<int, string>  $categoryPreferences
     * @return Collection<int, SeatHold>
     */
    public function handle(
        SeatMap $map,
        int $quantity,
        SeatingMode $mode,
        ?string $heldByType = null,
        ?string $heldById = null,
        ?string $reference = null,
        array $categoryPreferences = [],
    ): Collection {
        if ($quantity <= 0 || ! $mode->requiresAllocation()) {
            return new Collection;
        }

        return DB::transaction(function () use ($map, $quantity, $heldByType, $heldById, $reference, $categoryPreferences, $mode): Collection {
            $ttlMinutes = (int) config('seating.holds.ttl_minutes', 15);
            $expiresAt = now()->addMinutes($ttlMinutes);
            $holds = new Collection;

            for ($i = 0; $i < $quantity; $i++) {
                $seat = $this->pickSeat($map, $categoryPreferences, $holds, $mode);

                if ($seat === null) {
                    throw new InsufficientSeatsException(
                        "Could not hold {$quantity} seats; only " . $holds->count() . ' available.'
                    );
                }

                $hold = SeatHold::query()->create([
                    'seat_id' => $seat->id,
                    'held_by_type' => $heldByType,
                    'held_by_id' => $heldById,
                    'reference' => $reference,
                    'expires_at' => $expiresAt,
                ]);

                $holds->push($hold);
            }

            return $holds;
        });
    }

    /**
     * @param  array<int, string>  $preferences
     * @param  Collection<int, SeatHold>  $already
     */
    private function pickSeat(SeatMap $map, array $preferences, Collection $already, SeatingMode $mode): ?Seat
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

    /**
     * @param  Collection<int, SeatHold>  $already
     * @return Builder<Seat>
     */
    private function availableSeatsQuery(SeatMap $map, Collection $already): Builder
    {
        return Seat::query()
            ->whereHas('section', fn (Builder $query): Builder => $query->where('seat_map_id', $map->id))
            ->where('status', 'available')
            ->whereNotIn('id', $already->pluck('seat_id')->all())
            ->whereDoesntHave('holds', fn (Builder $query): Builder => $query->where('expires_at', '>', now()))
            ->orderBy('seat_section_id')
            ->orderBy('row_number')
            ->orderBy('column_number')
            ->lockForUpdate();
    }
}
