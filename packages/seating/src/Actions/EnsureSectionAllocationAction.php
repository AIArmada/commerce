<?php

declare(strict_types=1);

namespace AIArmada\Seating\Actions;

use AIArmada\Seating\Exceptions\SectionCapacityExceededException;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatSection;
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
            $activeCount = SeatAllocation::query()
                ->where('seat_section_id', $section->id)
                ->where('state', 'active')
                ->lockForUpdate()
                ->count();

            if ($activeCount >= $section->capacity) {
                throw new SectionCapacityExceededException(
                    "Section {$section->name} is at capacity ({$section->capacity})."
                );
            }

            return SeatAllocation::query()->create([
                'seat_section_id' => $section->id,
                'allocated_to_type' => $allocToType,
                'allocated_to_id' => $allocToId,
                'reference' => $reference,
                'allocated_at' => now(),
                'state' => 'active',
            ]);
        });
    }
}
