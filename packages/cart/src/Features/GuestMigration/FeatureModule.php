<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\GuestMigration;

use AIArmada\Cart\Features\GuestMigration\Actions\MigrateCartOnLoginAction;
use AIArmada\Cart\Features\GuestMigration\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Features\GuestMigration\EntryPoints\Listeners\HandleUserLogin;
use AIArmada\Cart\Features\GuestMigration\EntryPoints\Listeners\HandleUserLoginAttempt;
use AIArmada\Cart\Features\GuestMigration\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Features\GuestMigration\Services\CartMigrationService;
use AIArmada\Cart\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(CartMergeStrategyRegistry::class, function (): CartMergeStrategyRegistry {
            $registry = new CartMergeStrategyRegistry;
            $registry->registerBuiltIns();

            return $registry;
        });

        $app->bind(MigrateGuestCartToUserAction::class, fn ($app) => new MigrateGuestCartToUserAction(
            storage: null,
            strategyRegistry: $app->make(CartMergeStrategyRegistry::class),
        ));

        $app->bind(MigrateCartOnLoginAction::class, fn ($app) => new MigrateCartOnLoginAction(
            migrationAction: $app->make(MigrateGuestCartToUserAction::class),
        ));

        $app->scoped(CartMigrationService::class, fn ($app) => new CartMigrationService(
            config('cart.migration', []),
            migrationAction: $app->make(MigrateGuestCartToUserAction::class),
        ));
    }

    public function boot(Application $app): void
    {
        if (! config('cart.migration.auto_migrate_on_login', true)) {
            return;
        }

        $dispatcher = $app->make(Dispatcher::class);
        $dispatcher->listen(Attempting::class, HandleUserLoginAttempt::class);
        $dispatcher->listen(Login::class, HandleUserLogin::class);
    }
}
