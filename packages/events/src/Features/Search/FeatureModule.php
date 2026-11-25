<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search;

use AIArmada\Events\Contracts\EventSearchDocumentBuilder as EventSearchDocumentBuilderContract;
use AIArmada\Events\Contracts\EventSearchEngine;
use AIArmada\Events\Features\Search\EntryPoints\Observers\EventAttributeObserver;
use AIArmada\Events\Features\Search\EntryPoints\Observers\EventAudienceObserver;
use AIArmada\Events\Features\Search\EntryPoints\Observers\EventClassificationObserver;
use AIArmada\Events\Features\Search\EntryPoints\Observers\EventObserver;
use AIArmada\Events\Features\Search\EntryPoints\Observers\EventOccurrenceObserver;
use AIArmada\Events\Features\Search\EntryPoints\Observers\EventSessionObserver;
use AIArmada\Events\Features\Search\Models\EventSearchDocument;
use AIArmada\Events\Features\Search\Queries\EloquentEventSearchEngine;
use AIArmada\Events\Features\Search\Queries\EventSearchDocumentBuilder;
use AIArmada\Events\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $this->defaultModelConfig('search_document', EventSearchDocument::class);

        $app->bind(EventSearchDocumentBuilderContract::class, EventSearchDocumentBuilder::class);

        $this->bindSearchEngine($app);
    }

    public function boot(Application $app): void
    {
        ModelResolver::eventClass()::observe(EventObserver::class);
        ModelResolver::occurrenceClass()::observe(EventOccurrenceObserver::class);
        ModelResolver::sessionClass()::observe(EventSessionObserver::class);
        ModelResolver::attributeClass()::observe(EventAttributeObserver::class);
        ModelResolver::audienceClass()::observe(EventAudienceObserver::class);
        ModelResolver::classificationClass()::observe(EventClassificationObserver::class);
    }

    private function bindSearchEngine(Application $app): void
    {
        $configured = config('events.search.engine');

        if (is_string($configured) && $configured !== '') {
            if (! is_a($configured, EventSearchEngine::class, true)) {
                throw new RuntimeException('The events.search.engine config value must be an EventSearchEngine class.');
            }

            $app->bind(EventSearchEngine::class, $configured);

            return;
        }

        $app->bind(EventSearchEngine::class, EloquentEventSearchEngine::class);
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
