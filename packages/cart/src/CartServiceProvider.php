<?php

declare(strict_types=1);

namespace AIArmada\Cart;

use AIArmada\Cart\Actions\MigrateCartOnLoginAction;
use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Conditions\ConditionPresets;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Cart\Conditions\Handlers\ConditionTypeHandlerRegistry;
use AIArmada\Cart\Conditions\Handlers\ShippingConditionHandler;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineFactory;
use AIArmada\Cart\Contracts\CartSnapshotSyncInterface;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemConditionAdded;
use AIArmada\Cart\Events\ItemConditionRemoved;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Listeners\ApplyGlobalConditions;
use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Listeners\HandleUserLoginAttempt;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Services\CartFactory;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Services\CartMigrationService;
use AIArmada\Cart\Services\RulePresets;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Snapshots\CartSyncManager;
use AIArmada\Cart\Snapshots\CleanupSnapshotOnCartMerged;
use AIArmada\Cart\Snapshots\NormalizedCartSynchronizer;
use AIArmada\Cart\Snapshots\SyncCartOnEvent;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Support\LoginMigrationIdentifierResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Laravel\Octane\Events\RequestReceived;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CartServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('cart')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                Console\Commands\ClearAbandonedCartsCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->registerRulesFactory();
        $this->app->singleton(CartConditionResolver::class);
        $this->app->alias(CartConditionResolver::class, 'cart.condition_resolver');

        $this->app->singleton(ConditionProviderRegistry::class);
        $this->app->alias(ConditionProviderRegistry::class, 'cart.condition_providers');

        $this->registerStorage();
        $this->registerFactories();
        $this->registerConditionTypeHandlers();
        $this->registerCartManager();
        $this->registerMigrationService();
        $this->registerActions();
        $this->registerSnapshotServices();
    }

    public function bootingPackage(): void
    {
        $this->validateConfiguration('cart', [
            'money.default_currency',
        ]);

        $this->validateOwnerConfiguration();
        $this->registerEventListeners();
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

    protected function registerSnapshotServices(): void
    {
        $this->app->singleton(CartInstanceManager::class);
        $this->app->singleton(NormalizedCartSynchronizer::class);
        $this->app->singleton(CartSyncManager::class);
        $this->app->alias(CartSyncManager::class, CartSnapshotSyncInterface::class);
        $this->app->alias(CartSyncManager::class, 'cart.snapshot-sync');
    }

    protected function registerStorage(): void
    {
        $this->app->bind('cart.storage', function (Application $app) {
            $connection = $app->make(ConnectionResolverInterface::class)->connection();

            $storage = new DatabaseStorage(
                $connection,
                config('cart.database.table', 'carts'),
                config('cart.database.ttl'),
            );

            if (config('cart.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                if ($owner !== null) {
                    return $storage->withOwner($owner);
                }

                if (! OwnerContext::isExplicitGlobal()) {
                    throw new RuntimeException(
                        'Cart owner is enabled but no owner was resolved while resolving cart storage. ' .
                        'Use ' . OwnerContext::class . '::withOwner(null, ...) for explicit global cart access.'
                    );
                }
            }

            return $storage;
        });

        $this->app->bind(StorageInterface::class, fn ($app) => $app->make('cart.storage'));
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
            );
        });

        $this->app->alias('cart', CartManager::class);
        $this->app->alias('cart', Contracts\CartManagerInterface::class);
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
            events: $app->make(Dispatcher::class),
            eventsEnabled: config('cart.events', true),
        ));

        $this->app->singleton(ConditionPipelineFactory::class);
    }

    protected function registerMigrationService(): void
    {
        $this->app->singleton(CartMigrationService::class, fn (Application $app) => new CartMigrationService(
            config('cart.migration', []),
            migrationAction: $app->make(MigrateGuestCartToUserAction::class),
        ));

        $this->app->singleton(CartMergeStrategyRegistry::class, function () {
            $registry = new CartMergeStrategyRegistry;
            $registry->registerBuiltIns();

            return $registry;
        });
    }

    protected function registerActions(): void
    {
        $this->app->bind(MigrateGuestCartToUserAction::class, fn ($app) => new MigrateGuestCartToUserAction(
            storage: null,
            strategyRegistry: $app->make(CartMergeStrategyRegistry::class),
        ));

        $this->app->singleton(LoginMigrationIdentifierResolver::class);

        $this->app->bind(MigrateCartOnLoginAction::class, fn ($app) => new MigrateCartOnLoginAction(
            migrationAction: $app->make(MigrateGuestCartToUserAction::class),
            identifierResolver: $app->make(LoginMigrationIdentifierResolver::class),
        ));
    }

    protected function registerEventListeners(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        if (config('cart.migration.auto_migrate_on_login', true)) {
            $dispatcher->listen(Attempting::class, HandleUserLoginAttempt::class);
            $dispatcher->listen(Login::class, HandleUserLogin::class);
        }

        if (! config('cart.events', true)) {
            return;
        }

        if (config('cart.conditions.apply_global', true)) {
            $dispatcher->listen(CartCreated::class, [ApplyGlobalConditions::class, 'handleCartCreated']);
            $dispatcher->listen(ItemAdded::class, [ApplyGlobalConditions::class, 'handleItemChanged']);
            $dispatcher->listen(ItemUpdated::class, [ApplyGlobalConditions::class, 'handleItemChanged']);
            $dispatcher->listen(ItemRemoved::class, [ApplyGlobalConditions::class, 'handleItemChanged']);
        }

        $dispatcher->listen([
            CartCreated::class,
            CartCleared::class,
            CartDestroyed::class,
            ItemAdded::class,
            ItemUpdated::class,
            ItemRemoved::class,
            CartConditionAdded::class,
            CartConditionRemoved::class,
            ItemConditionAdded::class,
            ItemConditionRemoved::class,
        ], SyncCartOnEvent::class);
        $dispatcher->listen(CartMerged::class, CleanupSnapshotOnCartMerged::class);
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
