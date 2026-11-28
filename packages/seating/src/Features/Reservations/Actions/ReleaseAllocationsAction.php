<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Actions;

use AIArmada\Seating\Events\SeatAllocationReleased;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class ReleaseAllocationsAction
{
    /**
     * @return int Number of allocations released
     */
    public function handle(
        string $allocToType,
        string $allocToId,
        ?Model $actor = null,
        ?string $reason = null,
    ): int {
        return DB::transaction(function () use ($allocToType, $allocToId, $actor): int {
            $allocations = SeatAllocation::query()
                ->where('allocated_to_type', $allocToType)
                ->where('allocated_to_id', $allocToId)
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                $allocation->release(
                    releasedByType: $actor !== null ? $actor->getMorphClass() : null,
                    releasedById: $actor !== null ? $actor->getKey() : null,
                );

                DB::afterCommit(fn () => event(new SeatAllocationReleased($allocation)));
            }

            return $allocations->count();
        });
    }
}
