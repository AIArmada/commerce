<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\LiveCart;

use AIArmada\Cart\Features\LiveCart\Actions\DeleteAbandonedCarts;
use AIArmada\Cart\Features\LiveCart\Api\AbandonedCartCleanup;
use AIArmada\Cart\Features\LiveCart\Persistence\DatabaseStorage;
use AIArmada\Cart\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use RuntimeException;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind('cart.storage', function (Application $app) {
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

        $app->bind(StorageInterface::class, fn ($app) => $app->make('cart.storage'));
        $app->singleton(AbandonedCartCleanup::class, DeleteAbandonedCarts::class);
    }

    public function boot(Application $app): void {}
}
