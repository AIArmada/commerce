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
use InvalidArgumentException;

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

        if (($heldByType === null) !== ($heldById === null)) {
            throw new InvalidArgumentException('Held-by type and id must both be present or both be null.');
        }

        $this->assertOwnerContextForHoldCreation();

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
     * Select available seats with a locking re-check.
     *
     * The availability subquery reads from the transaction snapshot, so holds
     * committed after our snapshot (or by a transaction we waited on for a
     * seat lock) are invisible to it. The follow-up locking read sees the
     * latest committed hold rows and serializes concurrent selectors, and a
     * single retry fills from seats that were never contested.
     *
     * @param  array<int, string>  $preferences
     * @return Collection<int, Seat>
     */
    private function selectSeats(SeatMap $map, int $quantity, array $preferences): Collection
    {
        $excludedSeatIds = [];
        $verified = new Collection;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $candidates = $this->availableSeatsQuery($map, $preferences)
                ->when($excludedSeatIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludedSeatIds))
                ->limit($quantity)
                ->get();

            if ($candidates->isEmpty()) {
                break;
            }

            $heldSeatIds = SeatHold::query()
                ->whereIn('seat_id', $candidates->pluck('id')->all())
                ->where('expires_at', '>', CarbonImmutable::now())
                ->lockForUpdate()
                ->pluck('seat_id')
                ->all();

            $fresh = $candidates->reject(fn (Seat $seat): bool => in_array($seat->id, $heldSeatIds, true))->values();
            $verified = $verified->concat($fresh)->unique('id')->values();

            if ($verified->count() >= $quantity) {
                return $verified->take($quantity)->values();
            }

            $excludedSeatIds = array_values(array_unique([
                ...$excludedSeatIds,
                ...$candidates->pluck('id')->all(),
            ]));
        }

        return $verified;
    }

    private function assertOwnerContextForHoldCreation(): void
    {
        if (! SeatHold::ownerScopeConfig()->enabled) {
            return;
        }

        OwnerContext::assertResolvedOrExplicitGlobal(
            OwnerContext::resolve(),
            'Seat hold creation requires an owner context when seating owner mode is enabled. Use OwnerContext::withOwner($owner, ...) or OwnerContext::withOwner(null, ...) for explicit global holds.'
        );
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
     * @param  array<int, string>  $preferences
     * @return Builder<Seat>
     */
    private function availableSeatsQuery(SeatMap $map, array $preferences = []): Builder
    {
        $query = Seat::query()
            ->with('section')
            ->whereHas('section', fn (Builder $query): Builder => $query->where('seat_map_id', $map->id))
            ->available()
            ->whereDoesntHave('holds', fn (Builder $query): Builder => $query->where('expires_at', '>', CarbonImmutable::now()));

        if ($preferences !== []) {
            $placeholders = implode(',', array_fill(0, count($preferences), '?'));
            $query->orderByRaw("CASE WHEN category IN ({$placeholders}) THEN 0 ELSE 1 END", array_values($preferences));
        }

        // Contended seat rows are skipped instead of blocking: the retry loop
        // refills from seats that were never contested. The raw lock string
        // passes through on MySQL/Postgres and is ignored on SQLite.
        return $query
            ->orderBy('seat_section_id')
            ->orderBy('row_number')
            ->orderBy('column_number')
            ->lock('for update skip locked');
    }
}
