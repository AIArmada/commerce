<?php

declare(strict_types=1);

namespace AIArmada\Seating\Actions;

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

            return SeatAllocation::query()->create([
                'seat_section_id' => $lockedSection->getKey(),
                'allocated_to_type' => $allocToType,
                'allocated_to_id' => $allocToId,
                'reference' => $reference,
                'allocated_at' => CarbonImmutable::now(),
                'status' => 'active',
            ]);
        });
    }
}
