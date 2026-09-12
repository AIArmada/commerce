<?php

declare(strict_types=1);

namespace AIArmada\Seating\Actions;

use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\StaleSeatHoldException;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatHold;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConvertHoldsToAllocationsAction
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
                $lockedHold = SeatHold::query()
                    ->whereKey($hold->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedHold->isConverted()) {
                    continue;
                }

                if ($lockedHold->isExpired()) {
                    throw new StaleSeatHoldException(
                        "SeatHold {$lockedHold->id} has expired and cannot be converted."
                    );
                }

                $seat = Seat::query()
                    ->whereKey($lockedHold->seat_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (SeatAllocation::query()
                    ->where('seat_id', $seat->getKey())
                    ->where('status', 'active')
                    ->exists()) {
                    continue;
                }

                try {
                    $allocation = DB::transaction(function () use ($lockedHold, $mode, $allocToType, $allocToId, $reference, $seat): SeatAllocation {
                        return SeatAllocation::query()->create([
                            'seat_id' => $lockedHold->seat_id,
                            'seat_section_id' => $mode === SeatingMode::GeneralAdmission ? null : $seat->seat_section_id,
                            'allocated_to_type' => $allocToType,
                            'allocated_to_id' => $allocToId,
                            'reference' => $reference,
                            'allocated_at' => CarbonImmutable::now(),
                            'status' => 'active',
                        ]);
                    });
                } catch (QueryException $exception) {
                    if (! $this->isUniqueConstraintViolation($exception)) {
                        throw $exception;
                    }

                    continue;
                }

                $lockedHold->markConverted();

                $allocations->push($allocation);
            }

            return $allocations;
        });
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
