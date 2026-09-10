<?php

declare(strict_types=1);

namespace AIArmada\Seating\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
        if ($quantity <= 0 || ! $mode->requiresSeatAllocation()) {
            return new Collection;
        }

        return DB::transaction(function () use ($map, $quantity, $heldByType, $heldById, $reference, $categoryPreferences): Collection {
            $ttlMinutes = (int) config('seating.holds.ttl_minutes', 15);
            $expiresAt = CarbonImmutable::now()->addMinutes($ttlMinutes);
            $seats = $this->selectSeats($map, $quantity, $categoryPreferences);

            if ($seats->count() < $quantity) {
                throw new InsufficientSeatsException(
                    "Could not hold {$quantity} seats; only {$seats->count()} available."
                );
            }

            return $this->createHolds($seats, $expiresAt, $heldByType, $heldById, $reference);
        });
    }

    /**
     * @param  array<int, string>  $preferences
     * @return Collection<int, Seat>
     */
    private function selectSeats(SeatMap $map, int $quantity, array $preferences): Collection
    {
        $preferred = new Collection;

        if ($preferences !== []) {
            $preferred = $this->availableSeatsQuery($map)
                ->whereIn('category', $preferences)
                ->limit($quantity)
                ->get();
        }

        $remaining = $quantity - $preferred->count();

        if ($remaining <= 0) {
            return $preferred;
        }

        $fallback = $this->availableSeatsQuery($map)
            ->when($preferred->isNotEmpty(), fn (Builder $query): Builder => $query->whereNotIn('id', $preferred->pluck('id')->all()))
            ->limit($remaining)
            ->get();

        return $preferred->concat($fallback)->values();
    }

    /**
     * @param  Collection<int, Seat>  $seats
     * @return Collection<int, SeatHold>
     */
    private function createHolds(
        Collection $seats,
        CarbonImmutable $expiresAt,
        ?string $heldByType,
        ?string $heldById,
        ?string $reference,
    ): Collection {
        $now = Carbon::now();
        $owner = OwnerContext::resolve();
        /** @var Collection<int, SeatHold> $holds */
        $holds = new Collection;

        foreach ($seats as $seat) {
            $hold = new SeatHold;
            $hold->setUniqueIds();
            $hold->seat_id = $seat->id;
            $hold->held_by_type = $heldByType;
            $hold->held_by_id = $heldById;
            $hold->reference = $reference;
            $hold->expires_at = $expiresAt;
            $hold->created_at = $now;
            $hold->updated_at = $now;

            if ($owner !== null && config('seating.owner.auto_assign_on_create', true)) {
                $hold->assignOwner($owner);
            }

            $holds->push($hold);
        }

        $rows = [];

        foreach ($holds as $hold) {
            $rows[] = $hold->getAttributes();
        }

        SeatHold::query()->insert($rows);

        foreach ($holds as $hold) {
            $hold->exists = true;
            $hold->wasRecentlyCreated = true;
            $hold->syncOriginal();
        }

        return $holds;
    }

    /**
     * @return Builder<Seat>
     */
    private function availableSeatsQuery(SeatMap $map): Builder
    {
        return Seat::query()
            ->with('section')
            ->whereHas('section', fn (Builder $query): Builder => $query->where('seat_map_id', $map->id))
            ->available()
            ->whereDoesntHave('holds', fn (Builder $query): Builder => $query->where('expires_at', '>', CarbonImmutable::now()))
            ->orderBy('seat_section_id')
            ->orderBy('row_number')
            ->orderBy('column_number')
            ->lockForUpdate();
    }
}
