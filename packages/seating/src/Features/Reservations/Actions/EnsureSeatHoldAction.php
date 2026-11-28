<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final readonly class EnsureSeatHoldAction
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

        return $map->getConnection()->transaction(function () use ($map, $quantity, $heldByType, $heldById, $reference, $categoryPreferences): Collection {
            $map = SeatMap::query()->whereKey($map->getKey())->active()->firstOrFail();
            $ttlMinutes = max(1, (int) config('seating.holds.ttl_minutes', 15));
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
        $excludedSeatIds = [];
        $verified = new Collection;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $candidates = $this->availableSeatsQuery($map, $preferences)
                ->when($excludedSeatIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludedSeatIds))
                ->limit($quantity - $verified->count())
                ->get();

            if ($candidates->isEmpty()) {
                break;
            }

            $heldSeatIds = SeatHold::query()
                ->withoutOwnerScope()
                ->whereIn('seat_id', $candidates->pluck('id')->all())
                ->where('expires_at', '>', CarbonImmutable::now())
                ->whereNull('converted_at')
                ->lockForUpdate()
                ->pluck('seat_id')
                ->all();

            $allocatedSeatIds = SeatAllocation::query()
                ->withoutOwnerScope()
                ->whereIn('seat_id', $candidates->pluck('id')->all())
                ->where('status', 'active')
                ->lockForUpdate()
                ->pluck('seat_id')
                ->all();

            $unavailable = array_fill_keys([...$heldSeatIds, ...$allocatedSeatIds], true);
            $fresh = $candidates->reject(fn (Seat $seat): bool => isset($unavailable[$seat->id]))->values();
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

            $hold->setRelation('seat', $seat);
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
            ->whereDoesntHave('holds', fn (Builder $query): Builder => $query->withoutOwnerScope()->where('expires_at', '>', CarbonImmutable::now())->whereNull('converted_at'))
            ->whereDoesntHave('activeAllocation', fn (Builder $query): Builder => $query->withoutOwnerScope());

        if ($preferences !== []) {
            $placeholders = implode(',', array_fill(0, count($preferences), '?'));
            $query->orderByRaw("CASE WHEN category IN ({$placeholders}) THEN 0 ELSE 1 END", array_values($preferences));
        }

        // Contended seat rows are skipped instead of blocking: the retry loop
        // refills from seats that were never contested. The raw lock string
        // PostgreSQL applies this lock; SQLite ignores it.
        return $query
            ->orderBy('seat_section_id')
            ->orderBy('row_number')
            ->orderBy('column_number')
            ->lock('for update skip locked');
    }
}
