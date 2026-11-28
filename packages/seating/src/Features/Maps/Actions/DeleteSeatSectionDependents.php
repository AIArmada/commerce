<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps\Actions;

use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatSection;
use AIArmada\Seating\Features\Reservations\Api\ReservationsApi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final readonly class DeleteSeatSectionDependents
{
    public function __construct(private ReservationsApi $reservations) {}

    public function handle(SeatSection $section): void
    {
        $this->assertSameOwner($section);

        $this->reservations->deleteForSection(
            sectionId: (string) $section->getKey(),
            ownerType: $section->owner_type,
            ownerId: $section->owner_id,
        );

        Seat::query()
            ->where('seat_section_id', $section->getKey())
            ->orderBy('id')
            ->chunkById(500, function (iterable $seats): void {
                foreach ($seats as $seat) {
                    $seat->delete();
                }
            });
    }

    private function assertSameOwner(SeatSection $section): void
    {
        $mismatched = Seat::query()
            ->withoutOwnerScope()
            ->where('seat_section_id', $section->getKey())
            ->where(function (Builder $query) use ($section): Builder {
                if ($section->owner_type === null && $section->owner_id === null) {
                    return $query->whereNotNull('owner_type')->orWhereNotNull('owner_id');
                }

                return $query
                    ->where(fn (Builder $inner): Builder => $inner->whereNull('owner_type')->orWhereNull('owner_id'))
                    ->orWhere('owner_type', '!=', $section->owner_type)
                    ->orWhere('owner_id', '!=', $section->owner_id);
            })
            ->exists();

        if ($mismatched) {
            throw new AuthorizationException('The seat section has seats belonging to another owner.');
        }
    }
}
