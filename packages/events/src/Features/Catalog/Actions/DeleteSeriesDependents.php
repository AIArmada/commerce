<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Features\Catalog\Models\EventSeries;
use AIArmada\Events\Features\Catalog\Models\EventSeriesItem;
use AIArmada\Events\Features\Catalog\Models\EventSeriesRule;
use Illuminate\Support\Facades\DB;

final class DeleteSeriesDependents
{
    public function handle(EventSeries $series): void
    {
        DB::transaction(function () use ($series): void {
            EventSeriesItem::query()
                ->where('event_series_id', $series->getKey())
                ->chunkById(200, function (iterable $items): void {
                    foreach ($items as $item) {
                        $item->delete();
                    }
                });

            EventSeriesRule::query()
                ->where('event_series_id', $series->getKey())
                ->chunkById(200, function (iterable $rules): void {
                    foreach ($rules as $rule) {
                        $rule->delete();
                    }
                });
        });
    }
}
