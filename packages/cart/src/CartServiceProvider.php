<?php

declare(strict_types=1);

namespace AIArmada\Cart;

use AIArmada\Cart\Conditions\ConditionPresets;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Cart\Conditions\Handlers\ConditionTypeHandlerRegistry;
use AIArmada\Cart\Conditions\Handlers\ShippingConditionHandler;
use AIArmada\Cart\Runtime\Conditions\Pipeline\ConditionPipelineFactory;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Cart\Runtime\CartSnapshotSyncInterface;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Features\GuestMigration\Services\CartMigrationService;
use AIArmada\Cart\Features\Snapshots\Persistence\NormalizedCartSynchronizer;
use AIArmada\Cart\Features\Snapshots\Services\CartSyncManager;
use AIArmada\Cart\Platform\ExtensionRegistry;
use AIArmada\Cart\Platform\FeatureCatalog;
use AIArmada\Cart\Platform\FeatureModule;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Services\CartFactory;
use AIArmada\Cart\Services\RulePresets;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Octane\Events\RequestReceived;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CartServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    /** @var list<FeatureModule> */
    private array $modules = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('cart')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                EntryPoints\Commands\ClearAbandonedCartsCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->registerRulesFactory();
        $this->app->singleton(CartConditionResolver::class);
        $this->app->alias(CartConditionResolver::class, 'cart.condition_resolver');

        $this->app->singleton(ConditionProviderRegistry::class);
        $this->app->alias(ConditionProviderRegistry::class, 'cart.condition_providers');

        $this->registerFactories();
        $this->registerConditionTypeHandlers();
        $this->registerCartManager();
        $this->registerFeatures();
    }

    public function bootingPackage(): void
    {
        $this->validateConfiguration('cart', [
            'money.default_currency',
        ]);

        $this->validateOwnerConfiguration();
        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }

        $this->registerOctaneListeners();

        $this->app->booted(static function (): void {
            ConditionPresets::rememberOctaneDefaults();
            RulePresets::rememberOctaneDefaults();
        });
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'cart',
            Cart::class,
            CartManagerInterface::class,
            StorageInterface::class,
            CartMigrationService::class,
            CartConditionResolver::class,
            'cart.condition_resolver',
            ConditionProviderRegistry::class,
            'cart.condition_providers',
            'cart.storage',
            RulesFactoryInterface::class,
            CartInstanceManager::class,
            NormalizedCartSynchronizer::class,
            CartSyncManager::class,
            CartSnapshotSyncInterface::class,
            'cart.snapshot-sync',
        ];
    }

    /**
     * @throws RuntimeException If owner is enabled but resolver is not configured
     */
    protected function validateOwnerConfiguration(): void
    {
        if (! config('cart.owner.enabled', false)) {
            return;
        }

        if (! $this->app->bound(OwnerResolverInterface::class)) {
            throw new RuntimeException(
                'Cart owner is enabled but no resolver is bound. ' .
                'Bind ' . OwnerResolverInterface::class . ' (recommended via COMMERCE_OWNER_RESOLVER / commerce-support config).'
            );
        }
    }

    protected function registerRulesFactory(): void
    {
        $this->app->singleton(RulesFactoryInterface::class, function (Application $app): RulesFactoryInterface {
            $factoryClass = config('cart.dynamic_rules_factory', Services\BuiltInRulesFactory::class);

            return $app->make($factoryClass ?: Services\BuiltInRulesFactory::class);
        });
    }

    protected function registerCartManager(): void
    {
        $this->app->scoped('cart', function (Application $app) {
            return new CartManager(
                storage: $app->make('cart.storage'),
                events: $app->make(Dispatcher::class),
                eventsEnabled: config('cart.events', true),
                conditionResolver: $app->make(CartConditionResolver::class),
                cartFactory: $app->make(CartFactory::class),
                storageResolver: static function () use ($app): StorageInterface {
                    /** @var StorageInterface $storage */
                    $storage = $app->make('cart.storage');

                    return $storage;
                },
            );
        });

        $this->app->alias('cart', CartManagerInterface::class);
    }

    protected function registerConditionTypeHandlers(): void
    {
        $this->app->singleton(ConditionTypeHandlerRegistry::class, function () {
            $registry = new ConditionTypeHandlerRegistry;
            $registry->register(new ShippingConditionHandler);

            return $registry;
        });
    }

    protected function registerFactories(): void
    {
        $this->app->scoped(CartFactory::class, fn ($app) => new CartFactory(
            storage: $app->make('cart.storage'),
            conditionResolver: $app->make(CartConditionResolver::class),
            conditionProviderRegistry: $app->make(ConditionProviderRegistry::class),
            conditionTypeHandlerRegistry: $app->make(ConditionTypeHandlerRegistry::class),
            pipelineFactory: $app->make(ConditionPipelineFactory::class),
            events: $app->make(Dispatcher::class),
            eventsEnabled: config('cart.events', true),
        ));

        $this->app->singleton(ConditionPipelineFactory::class);
    }

    private function registerFeatures(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule');

            if (! $module instanceof FeatureModule) {
                throw new RuntimeException('Invalid cart feature module: ' . $feature);
            }

            $module->register($this->app);
            $this->modules[] = $module;
        }
    }

    private function registerOctaneListeners(): void
    {
        if (! class_exists(RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(RequestReceived::class, static function (): void {
            app()->forgetInstance('cart.storage');
            app()->forgetInstance('cart');
            app()->forgetInstance(CartFactory::class);
            ConditionPresets::restoreOctaneDefaults();
            RulePresets::restoreOctaneDefaults();
        });
    }
}
