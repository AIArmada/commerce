<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Events\Features\Venues\Models\Venue;
use AIArmada\Events\Features\Venues\Models\VenueFacility;
use Illuminate\Support\Facades\DB;

final class DeleteVenueDependents
{
    public function handle(Venue $venue): void
    {
        DB::transaction(function () use ($venue): void {
            VenueFacility::query()
                ->where('venue_id', $venue->getKey())
                ->chunkById(200, function (iterable $facilities): void {
                    foreach ($facilities as $facility) {
                        $facility->delete();
                    }
                });
        });
    }
}
