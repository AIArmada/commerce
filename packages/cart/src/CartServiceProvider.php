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
use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Listeners\HandleUserLoginAttempt;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Services\CartFactory;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Services\CartMigrationService;
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
        $this->app->singleton(CartFactory::class, fn ($app) => new CartFactory(
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
        $this->app->singleton(CartMigrationService::class, fn () => new CartMigrationService(
            config('cart.migration', []),
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
        if (! config('cart.migration.auto_migrate_on_login', true)) {
            return;
        }

        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->listen(Attempting::class, HandleUserLoginAttempt::class);
        $dispatcher->listen(Login::class, HandleUserLogin::class);
    }

    private function registerOctaneListeners(): void
    {
        if (! class_exists(RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(RequestReceived::class, static function (): void {
            ConditionPresets::restoreOctaneDefaults();
        });
    }
}
