<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps\Actions;

use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Features\Maps\Models\SeatSection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final readonly class DeleteSeatMapDependents
{
    public function handle(SeatMap $seatMap): void
    {
        $this->assertSameOwner($seatMap);

        SeatSection::query()
            ->where('seat_map_id', $seatMap->getKey())
            ->orderBy('id')
            ->chunkById(500, function (iterable $sections): void {
                foreach ($sections as $section) {
                    $section->delete();
                }
            });
    }

    private function assertSameOwner(SeatMap $seatMap): void
    {
        $mismatched = SeatSection::query()
            ->withoutOwnerScope()
            ->where('seat_map_id', $seatMap->getKey())
            ->where(function (Builder $query) use ($seatMap): Builder {
                if ($seatMap->owner_type === null && $seatMap->owner_id === null) {
                    return $query->whereNotNull('owner_type')->orWhereNotNull('owner_id');
                }

                return $query
                    ->where(fn (Builder $inner): Builder => $inner->whereNull('owner_type')->orWhereNull('owner_id'))
                    ->orWhere('owner_type', '!=', $seatMap->owner_type)
                    ->orWhere('owner_id', '!=', $seatMap->owner_id);
            })
            ->exists();

        if ($mismatched) {
            throw new AuthorizationException('The seat map has sections belonging to another owner.');
        }
    }
}
