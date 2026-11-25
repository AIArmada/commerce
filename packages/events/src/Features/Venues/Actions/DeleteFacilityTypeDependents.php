<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Events\Features\Venues\Models\EventFacility;
use AIArmada\Events\Features\Venues\Models\FacilityType;
use AIArmada\Events\Features\Venues\Models\VenueFacility;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteFacilityTypeDependents
{
    public function handle(FacilityType $type): void
    {
        $referenced = EventFacility::query()
            ->withoutOwnerScope()
            ->where('facility_type_id', $type->getKey())
            ->exists();

        if ($referenced) {
            throw new InvalidArgumentException(
                "Facility type [{$type->getAttribute('code')}] is referenced by event facilities and cannot be deleted. Set is_active=false to retire it instead."
            );
        }

        DB::transaction(function () use ($type): void {
            VenueFacility::query()
                ->where('facility_type_id', $type->getKey())
                ->chunkById(200, function (iterable $facilities): void {
                    foreach ($facilities as $facility) {
                        $facility->delete();
                    }
                });
        });
    }
}
