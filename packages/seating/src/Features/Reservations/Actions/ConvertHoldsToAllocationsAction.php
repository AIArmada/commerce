<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Exceptions\StaleSeatHoldException;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
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
        return DB::transaction(function () use ($holds, $mode, $allocToType, $allocToId, $reference): Collection {
            $allocations = new Collection;

            foreach ($holds as $hold) {
                $current = SeatHold::query()->whereKey($hold->getKey())->firstOrFail();

                if ($current->isConverted()) {
                    continue;
                }

                $seat = Seat::query()
                    ->whereKey($current->seat_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedHold = SeatHold::query()
                    ->whereKey($hold->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedHold->isConverted()) {
                    continue;
                }

                if ($lockedHold->seat_id !== $seat->getKey() || $seat->status !== SeatStatus::Available || $lockedHold->isExpired()) {
                    throw new StaleSeatHoldException(
                        "SeatHold {$lockedHold->id} is no longer eligible for conversion."
                    );
                }

                if ($this->hasActiveAllocation($seat)) {
                    continue;
                }

                try {
                    $allocation = DB::transaction(function () use ($lockedHold, $mode, $allocToType, $allocToId, $reference, $seat): SeatAllocation {
                        $allocation = new SeatAllocation([
                            'seat_id' => $lockedHold->seat_id,
                            'seat_section_id' => $mode === SeatingMode::GeneralAdmission ? null : $seat->seat_section_id,
                            'allocated_to_type' => $allocToType,
                            'allocated_to_id' => $allocToId,
                            'reference' => $reference,
                            'allocated_at' => CarbonImmutable::now(),
                            'status' => 'active',
                        ]);

                        // The allocation inherits the hold owner exactly instead
                        // of the ambient context, so batch conversions cannot
                        // misattribute ownership across owners. A global hold
                        // converts inside explicit global scope to stay global.
                        if ($lockedHold->owner_type !== null && $lockedHold->owner_id !== null) {
                            $allocation->owner_type = $lockedHold->owner_type;
                            $allocation->owner_id = $lockedHold->owner_id;
                            $allocation->save();
                        } else {
                            OwnerContext::withOwner(null, fn (): bool => $allocation->save());
                        }

                        // Mark the hold converted atomically with the
                        // allocation so a privileged-write refusal rolls the
                        // allocation back instead of leaving it orphaned.
                        $lockedHold->markConverted();

                        return $allocation;
                    });
                } catch (UniqueConstraintViolationException $exception) {
                    if (! $this->hasActiveAllocation($seat)) {
                        throw $exception;
                    }

                    continue;
                }

                $allocations->push($allocation);
            }

            return $allocations;
        });
    }

    private function hasActiveAllocation(Seat $seat): bool
    {
        return SeatAllocation::query()
            ->withoutOwnerScope()
            ->where('seat_id', $seat->getKey())
            ->where('status', 'active')
            ->exists();
    }
}
