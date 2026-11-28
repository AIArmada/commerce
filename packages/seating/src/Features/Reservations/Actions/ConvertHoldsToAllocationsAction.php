<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Contracts\SeatingMode;
use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Exceptions\SectionCapacityExceededException;
use AIArmada\Seating\Exceptions\StaleSeatHoldException;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use AIArmada\Seating\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ConvertHoldsToAllocationsAction
{
    /**
     * @param  iterable<SeatHold>  $holds
     * @return Collection<int, SeatAllocation>
     */
    public function handle(
        iterable $holds,
        SeatingMode $mode,
        string $allocToType,
        string $allocToId,
        ?string $reference = null,
    ): Collection {
        $requestedIds = [];

        foreach ($holds as $hold) {
            $requestedIds[] = (string) $hold->getKey();
        }

        $requestedIds = array_values(array_unique($requestedIds));

        if ($requestedIds === []) {
            return new Collection;
        }

        return DB::transaction(function () use ($requestedIds, $allocToType, $allocToId, $reference): Collection {
            $initialHolds = SeatHold::query()->whereKey($requestedIds)->get()->keyBy(fn (SeatHold $hold): string => (string) $hold->getKey());
            $missing = array_values(array_diff($requestedIds, $initialHolds->keys()->all()));

            if ($missing !== []) {
                throw (new ModelNotFoundException)->setModel(SeatHold::class, $missing);
            }

            $seatIds = $initialHolds->map(fn (SeatHold $hold): string => (string) $hold->seat_id)->unique()->sort()->values()->all();
            $seatClass = ModelResolver::seatClass();
            $seats = $seatClass::query()
                ->whereKey($seatIds)
                ->orderBy('seat_section_id')
                ->orderBy('row_number')
                ->orderBy('column_number')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Model $seat): string => (string) $seat->getKey());

            $lockedHolds = SeatHold::query()
                ->whereKey($requestedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (SeatHold $hold): string => (string) $hold->getKey());

            $lockedSeatIds = $lockedHolds->map(fn (SeatHold $hold): string => (string) $hold->seat_id)->unique()->sort()->values()->all();

            if ($lockedSeatIds !== $seatIds) {
                throw new StaleSeatHoldException('Seat hold targets changed while converting.');
            }

            $sectionIds = $seats->map(fn (Model $seat): string => (string) $seat->getAttribute('seat_section_id'))->unique()->sort()->values()->all();
            $sectionClass = ModelResolver::seatSectionClass();
            $sections = $sectionClass::query()
                ->whereKey($sectionIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Model $section): string => (string) $section->getKey());

            $activeAllocations = SeatAllocation::query()
                ->withoutOwnerScope()
                ->whereIn('seat_id', $seatIds)
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (SeatAllocation $allocation): string => (string) $allocation->seat_id);

            $sectionCounts = SeatAllocation::query()
                ->withoutOwnerScope()
                ->whereIn('seat_section_id', $sectionIds)
                ->where('status', 'active')
                ->selectRaw('seat_section_id, COUNT(*) as aggregate')
                ->groupBy('seat_section_id')
                ->pluck('aggregate', 'seat_section_id')
                ->map(fn (mixed $count): int => (int) $count)
                ->all();

            $now = CarbonImmutable::now();
            $allocations = [];
            foreach ($requestedIds as $holdId) {
                $hold = $lockedHolds->get($holdId);

                if (! $hold instanceof SeatHold || $hold->isConverted()) {
                    continue;
                }

                $seat = $seats->get((string) $hold->seat_id);
                $section = $seat instanceof Model ? $sections->get((string) $seat->getAttribute('seat_section_id')) : null;

                if (! $seat instanceof Model || ! $section instanceof Model) {
                    throw new StaleSeatHoldException("SeatHold {$holdId} references an unavailable seat.");
                }

                if ($hold->isExpired() || $seat->getAttribute('status') !== SeatStatus::Available) {
                    throw new StaleSeatHoldException("SeatHold {$holdId} is no longer eligible for conversion.");
                }

                $existing = $activeAllocations->get((string) $hold->seat_id);

                if ($existing instanceof SeatAllocation) {
                    if ($this->isSameTarget($existing, $allocToType, $allocToId, $hold)) {
                        continue;
                    }

                    throw new StaleSeatHoldException("SeatHold {$holdId} conflicts with an active allocation.");
                }

                $sectionId = (string) $section->getKey();
                $capacity = (int) $section->getAttribute('capacity');

                if (($sectionCounts[$sectionId] ?? 0) >= $capacity) {
                    throw new SectionCapacityExceededException(
                        "Section {$section->getAttribute('name')} is at capacity ({$capacity})."
                    );
                }

                $allocation = new SeatAllocation([
                    'seat_id' => $hold->seat_id,
                    'seat_section_id' => $sectionId,
                    'allocated_to_type' => $allocToType,
                    'allocated_to_id' => $allocToId,
                    'reference' => $reference,
                    'allocated_at' => $now,
                    'status' => 'active',
                ]);

                if ($hold->owner_type !== null && $hold->owner_id !== null) {
                    $allocation->owner_type = $hold->owner_type;
                    $allocation->owner_id = $hold->owner_id;
                } else {
                    if (! OwnerContext::isExplicitGlobal()) {
                        throw new AuthorizationException('Converting a global seat hold requires explicit global owner context.');
                    }
                }

                $allocation->save();
                $activeAllocations->put((string) $hold->seat_id, $allocation);

                SeatHold::query()->whereKey($hold->getKey())->update(['converted_at' => $now]);

                $sectionCounts[$sectionId] = ($sectionCounts[$sectionId] ?? 0) + 1;
                $allocations[] = $allocation;
            }

            return new Collection($allocations);
        });
    }

    private function isSameTarget(SeatAllocation $allocation, string $type, string $id, SeatHold $hold): bool
    {
        return $allocation->allocated_to_type === $type
            && (string) $allocation->allocated_to_id === $id
            && $allocation->owner_type === $hold->owner_type
            && (string) $allocation->owner_id === (string) $hold->owner_id;
    }
}
