<?php

declare(strict_types=1);

namespace AIArmada\Engagement;

use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Contracts\ShareUrlGenerator;
use AIArmada\Engagement\Features\Reminders\EntryPoints\Commands\SendDueRemindersCommand;
use AIArmada\Engagement\Features\Counters\EntryPoints\Commands\ReconcileEngagementCountersCommand;
use AIArmada\Engagement\Features\Subscriptions\EntryPoints\Commands\MatchSubscriptionsCommand;
use AIArmada\Engagement\Integrations\Events\EngagementEventEngagementManager;
use AIArmada\Engagement\Platform\ExtensionRegistry;
use AIArmada\Engagement\Platform\FeatureCatalog;
use AIArmada\Engagement\Platform\FeatureModule;
use AIArmada\Engagement\Services\DefaultEngagementPolicyResolver;
use AIArmada\Engagement\Services\DefaultEngagementStateResolver;
use AIArmada\Engagement\Services\DefaultShareUrlGenerator;
use AIArmada\Events\Contracts\EventEngagementManager;
use AIArmada\Events\EventsServiceProvider;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EngagementServiceProvider extends PackageServiceProvider
{
    /** @var array<string, FeatureModule> */
    private array $featureModules = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('engagement')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                MatchSubscriptionsCommand::class,
                ReconcileEngagementCountersCommand::class,
                SendDueRemindersCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton('engagement', fn ($app): ManageInteractionsAction => $app->make(ManageInteractionsAction::class));

        $this->app->bind(EngagementStateResolver::class, DefaultEngagementStateResolver::class);
        $this->app->bind(EngagementPolicyResolver::class, DefaultEngagementPolicyResolver::class);
        $this->app->bind(ShareUrlGenerator::class, DefaultShareUrlGenerator::class);

        $this->registerEventsIntegration();

        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $class = __NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule';
            $module = $this->app->make($class);

            if (! $module instanceof FeatureModule) {
                throw new InvalidArgumentException("Invalid engagement feature module [{$class}].");
            }

            $this->featureModules[$feature] = $module;
            $module->register($this->app);
        }
    }

    public function bootingPackage(): void
    {
        foreach ($this->featureModules as $module) {
            $module->boot($this->app);
        }
    }

    private function registerEventsIntegration(): void
    {
        if (! class_exists(EventsServiceProvider::class)) {
            return;
        }

        if (! interface_exists(EventEngagementManager::class)) {
            return;
        }

        $this->app->bind(
            EventEngagementManager::class,
            EngagementEventEngagementManager::class,
        );
    }

}
