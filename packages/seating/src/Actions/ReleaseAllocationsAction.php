<?php

declare(strict_types=1);

namespace AIArmada\Seating\Actions;

use AIArmada\Seating\Events\SeatAllocationReleased;
use AIArmada\Seating\Models\SeatAllocation;
use Illuminate\Database\Eloquent\Model;

class ReleaseAllocationsAction
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
        $allocations = SeatAllocation::query()
            ->where('allocated_to_type', $allocToType)
            ->where('allocated_to_id', $allocToId)
            ->where('state', 'active')
            ->get();

        foreach ($allocations as $allocation) {
            $allocation->release(
                releasedByType: $actor !== null ? $actor->getMorphClass() : null,
                releasedById: $actor !== null ? $actor->getKey() : null,
            );

            event(new SeatAllocationReleased($allocation));
        }

        return $allocations->count();
    }
}
