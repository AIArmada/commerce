<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Actions;

use AIArmada\Seating\Data\SeatAllocationData;
use AIArmada\Seating\Events\SeatAllocationReleased;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

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
        return (new SeatAllocation)->getConnection()->transaction(function () use ($allocToType, $allocToId, $actor, $reason): int {
            $allocations = SeatAllocation::query()
                ->where('allocated_to_type', $allocToType)
                ->where('allocated_to_id', $allocToId)
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $now = CarbonImmutable::now();

            foreach ($allocations as $allocation) {
                $allocation->status = 'released';
                $allocation->released_at = $now;

                if ($actor !== null) {
                    $allocation->released_by_type = $actor->getMorphClass();
                    $allocation->released_by_id = (string) $actor->getKey();
                }

                if ($reason !== null) {
                    $allocation->metadata = [...($allocation->metadata ?? []), 'release_reason' => $reason];
                }

                $allocation->save();

                $payload = new SeatAllocationData(
                    id: (string) $allocation->getKey(),
                    seatId: $allocation->seat_id !== null ? (string) $allocation->seat_id : null,
                    sectionId: $allocation->seat_section_id !== null ? (string) $allocation->seat_section_id : null,
                    allocatedToType: $allocation->allocated_to_type,
                    allocatedToId: $allocation->allocated_to_id !== null ? (string) $allocation->allocated_to_id : null,
                    reference: $allocation->reference,
                    status: $allocation->status,
                    allocatedAt: $allocation->allocated_at,
                    releasedAt: $allocation->released_at,
                );

                $allocation->getConnection()->afterCommit(fn () => event(new SeatAllocationReleased($payload)));
            }

            return $allocations->count();
        });
    }
}
