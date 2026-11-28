<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Exceptions\SectionCapacityExceededException;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class EnsureSectionAllocationAction
{
    public function handle(
        string $sectionId,
        string $allocToType,
        string $allocToId,
        ?string $reference = null,
    ): SeatAllocation {
        return DB::transaction(function () use ($sectionId, $allocToType, $allocToId, $reference): SeatAllocation {
            $sectionClass = ModelResolver::seatSectionClass();
            $lockedSection = $sectionClass::query()
                ->lockForUpdate()
                ->findOrFail($sectionId);

            $activeCount = SeatAllocation::query()
                ->withoutOwnerScope()
                ->where('seat_section_id', $lockedSection->getKey())
                ->where('status', 'active')
                ->count();

            $capacity = (int) $lockedSection->getAttribute('capacity');

            if ($activeCount >= $capacity) {
                throw new SectionCapacityExceededException(
                    sprintf('Section %s is at capacity (%d).', $lockedSection->getAttribute('name'), $capacity)
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

            $config = SeatAllocation::ownerScopeConfig();
            $contextOwner = $config->enabled ? OwnerContext::resolveConfigured($config) : null;

            if ($config->enabled) {
                $sectionOwnerType = $lockedSection->getAttribute('owner_type');
                $sectionOwnerId = $lockedSection->getAttribute('owner_id');

                if (($sectionOwnerType === null) !== ($sectionOwnerId === null)) {
                    throw new InvalidArgumentException('The seat section has an incomplete owner tuple.');
                }

                if ($sectionOwnerType !== null && $sectionOwnerId !== null) {
                    $allocation->setAttribute('owner_type', $sectionOwnerType);
                    $allocation->setAttribute('owner_id', $sectionOwnerId);
                } elseif ($contextOwner !== null) {
                    $allocation->assignOwner($contextOwner);
                } else {
                    OwnerContext::assertResolvedOrExplicitGlobal($contextOwner);
                }
            }

            $allocation->save();

            return $allocation;
        });
    }
}
