<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog;

use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Contracts\EventReferenceResolver;
use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\Events\Contracts\EventTranslationProvider;
use AIArmada\Events\Features\Catalog\Actions\DefaultEventCloneService;
use AIArmada\Events\Features\Catalog\Actions\DefaultEventLifecycleWorkflow;
use AIArmada\Events\Features\Catalog\Actions\DefaultEventTemplateService;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventAttribute;
use AIArmada\Events\Features\Catalog\Models\EventAudience;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\Policies\EventOccurrencePolicy;
use AIArmada\Events\Features\Catalog\Policies\EventPolicy;
use AIArmada\Events\Features\Catalog\Policies\EventSessionPolicy;
use AIArmada\Events\Features\Catalog\Queries\DefaultEventReferenceResolver;
use AIArmada\Events\Features\Catalog\Queries\DefaultEventTranslationProvider;
use AIArmada\Events\Features\Catalog\Queries\EloquentCatalogApi;
use AIArmada\Events\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $this->defaultModelConfig('event', Event::class);
        $this->defaultModelConfig('occurrence', EventOccurrence::class);
        $this->defaultModelConfig('session', EventSession::class);
        $this->defaultModelConfig('attribute', EventAttribute::class);
        $this->defaultModelConfig('audience', EventAudience::class);

        $app->singleton(EventLifecycleWorkflow::class, DefaultEventLifecycleWorkflow::class);
        $app->singleton(EventCloneService::class, DefaultEventCloneService::class);
        $app->singleton(EventTemplateService::class, DefaultEventTemplateService::class);

        if (config('events.references.resolver') === null) {
            $app->bind(EventReferenceResolver::class, DefaultEventReferenceResolver::class);
        }

        if (config('events.translations.provider') === null) {
            $app->bind(EventTranslationProvider::class, DefaultEventTranslationProvider::class);
        }

        $app->bind(CatalogApi::class, EloquentCatalogApi::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(ModelResolver::eventClass(), EventPolicy::class);
        Gate::policy(ModelResolver::occurrenceClass(), EventOccurrencePolicy::class);
        Gate::policy(ModelResolver::sessionClass(), EventSessionPolicy::class);
    }

    /**
     * @param  class-string  $model
     */
    private function defaultModelConfig(string $key, string $model): void
    {
        if (config('events.models.' . $key) !== null) {
            return;
        }

        config(['events.models.' . $key => $model]);
    }
}
