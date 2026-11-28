<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Persistence;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class SeatReservationCleaner
{
    public function deleteForSeat(string $seatId, ?string $ownerType, string | int | null $ownerId): int
    {
        return DB::transaction(function () use ($seatId, $ownerType, $ownerId): int {
            $holds = SeatHold::query()->withoutOwnerScope()->where('seat_id', $seatId)->get();
            $allocations = SeatAllocation::query()->withoutOwnerScope()->where('seat_id', $seatId)->get();

            $this->assertSameOwner($holds, $ownerType, $ownerId);
            $this->assertSameOwner($allocations, $ownerType, $ownerId);

            $deleted = 0;

            foreach ($holds as $hold) {
                $deleted += (int) $hold->delete();
            }

            foreach ($allocations as $allocation) {
                $deleted += (int) $allocation->delete();
            }

            return $deleted;
        });
    }

    public function deleteForSection(string $sectionId, ?string $ownerType, string | int | null $ownerId): int
    {
        return DB::transaction(function () use ($sectionId, $ownerType, $ownerId): int {
            $allocations = SeatAllocation::query()->withoutOwnerScope()->where('seat_section_id', $sectionId)->get();
            $this->assertSameOwner($allocations, $ownerType, $ownerId);

            $deleted = 0;

            foreach ($allocations as $allocation) {
                $deleted += (int) $allocation->delete();
            }

            return $deleted;
        });
    }

    /**
     * @param  iterable<Model>  $rows
     */
    private function assertSameOwner(iterable $rows, ?string $ownerType, string | int | null $ownerId): void
    {
        $contextOwner = OwnerContext::resolve();

        if ($ownerType === null && $ownerId === null) {
            OwnerContext::assertResolvedOrExplicitGlobal(null, 'Explicit global owner context is required to delete global seating records.');
        } elseif ($contextOwner === null
            || $contextOwner->getMorphClass() !== $ownerType
            || (string) $contextOwner->getKey() !== (string) $ownerId) {
            throw new AuthorizationException('The seating records are not accessible in the current owner scope.');
        }

        foreach ($rows as $row) {
            $rowType = $row->getAttribute('owner_type');
            $rowId = $row->getAttribute('owner_id');

            if (($rowType === null) !== ($rowId === null)) {
                throw new AuthorizationException('A seating record has an incomplete owner tuple.');
            }

            if ($ownerType === null && $ownerId === null) {
                if ($rowType !== null || $rowId !== null) {
                    throw new AuthorizationException('A seating record belongs to another owner.');
                }

                continue;
            }

            if ($rowType !== $ownerType || (string) $rowId !== (string) $ownerId) {
                throw new AuthorizationException('A seating record belongs to another owner.');
            }
        }
    }
}
