<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes;

use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Features\Changes\Actions\DefaultEventChangeNoticeWorkflow;
use AIArmada\Events\Features\Changes\Api\ChangesApi;
use AIArmada\Events\Features\Changes\EntryPoints\Listeners\DispatchEventChangeNoticeNotifications;
use AIArmada\Events\Features\Changes\Models\EventChangeLog;
use AIArmada\Events\Features\Changes\Models\EventUpdate;
use AIArmada\Events\Features\Changes\Queries\DefaultEventChangeNoticeAudienceResolver;
use AIArmada\Events\Features\Changes\Queries\EloquentChangesApi;
use AIArmada\Events\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $this->defaultModelConfig('change_log', EventChangeLog::class);
        $this->defaultModelConfig('update', EventUpdate::class);

        $app->singleton(EventChangeNoticeWorkflow::class, DefaultEventChangeNoticeWorkflow::class);

        $app->bind(ChangesApi::class, EloquentChangesApi::class);

        $this->bindAudienceResolver($app);
    }

    public function boot(Application $app): void
    {
        $app->make(Dispatcher::class)
            ->listen(EventChangeNoticePublished::class, DispatchEventChangeNoticeNotifications::class);
    }

    private function bindAudienceResolver(Application $app): void
    {
        $configured = config('events.change_notices.audience_resolver');

        if (is_string($configured) && $configured !== '') {
            if (! is_a($configured, EventChangeNoticeAudienceResolver::class, true)) {
                throw new RuntimeException('The events.change_notices.audience_resolver config value must be an EventChangeNoticeAudienceResolver class.');
            }

            $app->bind(EventChangeNoticeAudienceResolver::class, $configured);

            return;
        }

        $app->bind(EventChangeNoticeAudienceResolver::class, DefaultEventChangeNoticeAudienceResolver::class);
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
