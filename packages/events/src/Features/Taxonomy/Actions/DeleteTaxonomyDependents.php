<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use Illuminate\Support\Facades\DB;

final class DeleteTaxonomyDependents
{
    public function handle(EventTaxonomy $taxonomy): void
    {
        DB::transaction(function () use ($taxonomy): void {
            EventClassification::query()
                ->where('event_taxonomy_id', $taxonomy->getKey())
                ->chunkById(200, function (iterable $classifications): void {
                    foreach ($classifications as $classification) {
                        $classification->delete();
                    }
                });

            EventTerm::query()
                ->where('event_taxonomy_id', $taxonomy->getKey())
                ->chunkById(200, function (iterable $terms): void {
                    foreach ($terms as $term) {
                        $term->delete();
                    }
                });
        });
    }
}
