<?php

declare(strict_types=1);

namespace AIArmada\Events;

use AIArmada\Events\Contracts\EventAuthzDriver;
use AIArmada\Events\Contracts\EventCartDriver;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Contracts\EventClassificationResolver;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventEngagementManager;
use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Contracts\EventReferenceResolver;
use AIArmada\Events\Contracts\EventScheduleResolver;
use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Contracts\EventSearchRelationProvider;
use AIArmada\Events\Contracts\EventSeatingDriver;
use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Contracts\EventTranslationProvider;
use AIArmada\Events\Contracts\ResolvesEventTimeExpression;
use AIArmada\Events\Drivers\AuthzDriver;
use AIArmada\Events\Drivers\CartCheckoutDriver;
use AIArmada\Events\Drivers\DefaultEventDisplayTimezoneDriver;
use AIArmada\Events\Drivers\DefaultEventSearchPayloadDriver;
use AIArmada\Events\Drivers\EventChangeNoticeNotificationDriver;
use AIArmada\Events\Drivers\NullEventEngagementDriver;
use AIArmada\Events\Drivers\NullEventScheduleDriver;
use AIArmada\Events\Drivers\NullEventSearchIndexerDriver;
use AIArmada\Events\Drivers\OrdersDriver;
use AIArmada\Events\Drivers\SeatingDriver;
use AIArmada\Events\Drivers\TicketingDriver;
use AIArmada\Events\Features\Search\Actions\IndexEventSearchTargetAction;
use AIArmada\Events\Platform\DefaultEventSearchRelationProvider;
use AIArmada\Events\Platform\EventSubmissionOwnerScope;
use AIArmada\Events\Platform\ExtensionRegistry;
use AIArmada\Events\Platform\FeatureCatalog;
use AIArmada\Events\Platform\FeatureModule;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EventsServiceProvider extends PackageServiceProvider
{
    private const string ENGAGEMENT_PROVIDER = 'AIArmada\\Engagement\\EngagementServiceProvider';

    private const string ENGAGEMENT_MANAGER_DRIVER = 'AIArmada\\Engagement\\Integrations\\Events\\EngagementEventEngagementManager';

    /** @var array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>}|null */
    private ?array $catalog = null;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('events')
            ->hasConfigFile()
            ->hasViews()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $catalog = $this->catalog();

        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry(
            $app,
            $catalog['extensions'],
        ));

        foreach (array_keys($catalog['features']) as $name) {
            $instance = $this->app->make($this->moduleClass($name));

            if ($instance instanceof FeatureModule) {
                $instance->register($this->app);
            }
        }

        $this->registerConfiguredOverrides();
        $this->registerDrivers();
    }

    public function packageBooted(): void
    {
        foreach (array_keys($this->catalog()['features']) as $name) {
            $instance = $this->app->make($this->moduleClass($name));

            if ($instance instanceof FeatureModule) {
                $instance->boot($this->app);
            }
        }

        EventSubmissionOwnerScope::register();

        $this->registerMorphMap();
    }

    private function moduleClass(string $feature): string
    {
        return __NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule';
    }

    /**
     * @return array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>}
     */
    private function catalog(): array
    {
        return $this->catalog ??= FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
    }

    private function registerConfiguredOverrides(): void
    {
        $this->bindInterfaceWhenConfigured(EventReferenceResolver::class, 'events.references.resolver');
        $this->bindInterfaceWhenConfigured(EventClassificationResolver::class, 'events.classifications.resolver');
        $this->bindInterfaceWhenConfigured(EventTranslationProvider::class, 'events.translations.provider');
        $this->bindInterfaceWhenConfigured(ResolvesEventTimeExpression::class, 'events.time_expressions.resolver');
    }

    private function registerDrivers(): void
    {
        $this->app->bind(EventTicketingDriver::class, TicketingDriver::class);
        $this->app->bind(EventSeatingDriver::class, SeatingDriver::class);
        $this->app->bind(EventOrderDriver::class, OrdersDriver::class);
        $this->app->bind(EventCartDriver::class, CartCheckoutDriver::class);
        $this->app->bind(EventAuthzDriver::class, AuthzDriver::class);

        $this->bindEngagementManager();

        $this->bindDriver(
            EventScheduleResolver::class,
            'events.schedule.resolver',
            NullEventScheduleDriver::class,
        );

        $this->bindDriver(
            EventDisplayTimezoneResolver::class,
            'events.timezone.display_timezone_resolver',
            DefaultEventDisplayTimezoneDriver::class,
        );

        $this->bindDriver(
            EventSearchPayloadResolver::class,
            'events.search.payload_resolver',
            DefaultEventSearchPayloadDriver::class,
        );

        $this->bindDriver(
            EventSearchRelationProvider::class,
            'events.search.relation_provider',
            DefaultEventSearchRelationProvider::class,
        );

        $this->app->bind(EventChangeNoticeNotificationDispatcher::class, EventChangeNoticeNotificationDriver::class);
        $this->app->bind(EventSearchIndexer::class, fn (): EventSearchIndexer => $this->app->make($this->searchIndexerClass()));
    }

    private function bindEngagementManager(): void
    {
        if (class_exists(self::ENGAGEMENT_PROVIDER) && class_exists(self::ENGAGEMENT_MANAGER_DRIVER)) {
            $this->app->bind(EventEngagementManager::class, self::ENGAGEMENT_MANAGER_DRIVER);

            return;
        }

        $this->app->bind(EventEngagementManager::class, NullEventEngagementDriver::class);
    }

    /**
     * @param  class-string  $contract
     * @param  class-string  $default
     */
    private function bindDriver(string $contract, string $configKey, string $default): void
    {
        $configured = config($configKey);

        if (is_string($configured) && $configured !== '') {
            if (! is_a($configured, $contract, true)) {
                throw new RuntimeException(sprintf('The %s config value must be a %s class.', $configKey, $contract));
            }

            $this->app->bind($contract, $configured);

            return;
        }

        $this->app->bind($contract, $default);
    }

    /**
     * @param  class-string  $contract
     */
    private function bindInterfaceWhenConfigured(string $contract, string $configKey): void
    {
        $configured = config($configKey);

        if (! is_string($configured) || $configured === '') {
            return;
        }

        if (! is_a($configured, $contract, true)) {
            throw new RuntimeException(sprintf('The %s config value must be a %s class.', $configKey, $contract));
        }

        $this->app->bind($contract, $configured);
    }

    /**
     * @return class-string<EventSearchIndexer>
     */
    private function searchIndexerClass(): string
    {
        $configured = config('events.search.indexer');

        if (is_string($configured) && $configured !== '') {
            if (! is_a($configured, EventSearchIndexer::class, true)) {
                throw new RuntimeException('The events.search.indexer config value must be an EventSearchIndexer class.');
            }

            return $configured;
        }

        if (class_exists(IndexEventSearchTargetAction::class)) {
            return IndexEventSearchTargetAction::class;
        }

        return NullEventSearchIndexerDriver::class;
    }

    private function registerMorphMap(): void
    {
        Relation::morphMap([
            'event_registration' => ModelResolver::registrationClass(),
            'event_submission' => ModelResolver::submissionClass(),
        ]);
    }
}
