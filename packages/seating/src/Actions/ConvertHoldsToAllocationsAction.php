<?php

declare(strict_types=1);

namespace AIArmada\Seating\Actions;

use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\StaleSeatHoldException;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatHold;
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
                if ($hold->isConverted()) {
                    continue;
                }

                if ($hold->isExpired()) {
                    throw new StaleSeatHoldException(
                        "SeatHold {$hold->id} has expired and cannot be converted."
                    );
                }

                $allocation = SeatAllocation::query()->create([
                    'seat_id' => $hold->seat_id,
                    'seat_section_id' => $mode === SeatingMode::GeneralAdmission ? null : $hold->seat?->seat_section_id,
                    'allocated_to_type' => $allocToType,
                    'allocated_to_id' => $allocToId,
                    'reference' => $reference,
                    'allocated_at' => now(),
                    'state' => 'active',
                ]);

                $hold->markConverted();

                $allocations->push($allocation);
            }

            return $allocations;
        });
    }
}
