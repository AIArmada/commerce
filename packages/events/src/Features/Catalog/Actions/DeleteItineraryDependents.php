<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Features\Catalog\Models\EventItinerary;
use AIArmada\Events\Features\Catalog\Models\EventItineraryItem;
use Illuminate\Support\Facades\DB;

final class DeleteItineraryDependents
{
    public function handle(EventItinerary $itinerary): void
    {
        DB::transaction(function () use ($itinerary): void {
            EventItineraryItem::query()
                ->where('event_itinerary_id', $itinerary->getKey())
                ->chunkById(200, function (iterable $items): void {
                    foreach ($items as $item) {
                        $item->delete();
                    }
                });
        });
    }
}
