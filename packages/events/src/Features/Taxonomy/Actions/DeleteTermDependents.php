<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use AIArmada\Events\Features\Taxonomy\Models\EventTermPolicy;
use Illuminate\Support\Facades\DB;

final class DeleteTermDependents
{
    public function handle(EventTerm $term): void
    {
        DB::transaction(function () use ($term): void {
            EventClassification::query()
                ->where('event_term_id', $term->getKey())
                ->chunkById(200, function (iterable $classifications): void {
                    foreach ($classifications as $classification) {
                        $classification->delete();
                    }
                });

            EventTerm::query()
                ->where('parent_id', $term->getKey())
                ->chunkById(200, function (iterable $children): void {
                    foreach ($children as $child) {
                        $child->delete();
                    }
                });

            EventTermPolicy::query()
                ->where('event_term_id', $term->getKey())
                ->chunkById(200, function (iterable $policies): void {
                    foreach ($policies as $policy) {
                        $policy->delete();
                    }
                });
        });
    }
}
