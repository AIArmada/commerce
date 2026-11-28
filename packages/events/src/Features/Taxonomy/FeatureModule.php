<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy;

use AIArmada\Events\Contracts\EventClassificationResolver;
use AIArmada\Events\Contracts\EventTaxonomyHierarchy;
use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use AIArmada\Events\Features\Taxonomy\Queries\DefaultEventClassificationResolver;
use AIArmada\Events\Features\Taxonomy\Queries\EventTaxonomyHierarchyService;
use AIArmada\Events\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Events\Platform\SharedCatalogPolicy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        if (config('events.models.classification') === null) {
            config(['events.models.classification' => EventClassification::class]);
        }

        $app->singleton(EventTaxonomyHierarchy::class, EventTaxonomyHierarchyService::class);

        if (config('events.classifications.resolver') === null) {
            $app->bind(EventClassificationResolver::class, DefaultEventClassificationResolver::class);
        }
    }

    public function boot(Application $app): void
    {
        foreach ([EventTaxonomy::class, EventTerm::class] as $model) {
            if (Gate::getPolicyFor($model) === null) {
                Gate::policy($model, SharedCatalogPolicy::class);
            }
        }

        EventTaxonomy::saved(EventTaxonomyHierarchyService::flushRequestCache(...));
        EventTaxonomy::deleted(EventTaxonomyHierarchyService::flushRequestCache(...));
        EventTerm::saved(EventTaxonomyHierarchyService::flushRequestCache(...));
        EventTerm::deleted(EventTaxonomyHierarchyService::flushRequestCache(...));
    }
}
