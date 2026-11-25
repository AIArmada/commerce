<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Events\Features\Venues\Models\VenueFacility;
use AIArmada\Events\Features\Venues\Models\VenueSpace;
use Illuminate\Support\Facades\DB;

final class DeleteVenueSpaceDependents
{
    public function handle(VenueSpace $space): void
    {
        DB::transaction(function () use ($space): void {
            VenueFacility::query()
                ->where('venue_space_id', $space->getKey())
                ->chunkById(200, function (iterable $facilities): void {
                    foreach ($facilities as $facility) {
                        $facility->delete();
                    }
                });
        });
    }
}
