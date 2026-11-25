<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Events\Features\Venues\Models\EventFacility;
use AIArmada\Events\Features\Venues\Models\EventLocation;
use Illuminate\Support\Facades\DB;

final class DeleteEventLocationDependents
{
    public function handle(EventLocation $location): void
    {
        DB::transaction(function () use ($location): void {
            EventFacility::query()
                ->where('event_location_id', $location->getKey())
                ->chunkById(200, function (iterable $facilities): void {
                    foreach ($facilities as $facility) {
                        $facility->delete();
                    }
                });
        });
    }
}
