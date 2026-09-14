<?php

declare(strict_types=1);

namespace AIArmada\Seating\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Exceptions\SectionCapacityExceededException;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatSection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EnsureSectionAllocationAction
{
    public function handle(
        SeatSection $section,
        string $allocToType,
        string $allocToId,
        ?string $reference = null,
    ): SeatAllocation {
        return DB::transaction(function () use ($section, $allocToType, $allocToId, $reference): SeatAllocation {
            $lockedSection = SeatSection::query()
                ->lockForUpdate()
                ->findOrFail($section->getKey());

            $activeCount = SeatAllocation::query()
                ->where('seat_section_id', $lockedSection->getKey())
                ->where('status', 'active')
                ->count();

            if ($activeCount >= $lockedSection->capacity) {
                throw new SectionCapacityExceededException(
                    "Section {$lockedSection->name} is at capacity ({$lockedSection->capacity})."
                );
            }

            $allocation = new SeatAllocation([
                'seat_section_id' => $lockedSection->getKey(),
                'allocated_to_type' => $allocToType,
                'allocated_to_id' => $allocToId,
                'reference' => $reference,
                'allocated_at' => CarbonImmutable::now(),
                'status' => 'active',
            ]);

            // The allocation inherits the section owner exactly instead of
            // the ambient context, so conversions cannot misattribute
            // ownership across owners. A global section allocates inside
            // explicit global scope to stay global.
            if ($lockedSection->owner_type !== null && $lockedSection->owner_id !== null) {
                $allocation->owner_type = $lockedSection->owner_type;
                $allocation->owner_id = $lockedSection->owner_id;
                $allocation->save();

                return $allocation;
            }

            OwnerContext::withOwner(null, fn (): bool => $allocation->save());

            return $allocation;
        });
    }
}
