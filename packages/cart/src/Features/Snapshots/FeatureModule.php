<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots;

use AIArmada\Cart\Contracts\CartSnapshotSyncInterface;
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
use AIArmada\Cart\Features\Snapshots\EntryPoints\Listeners\CleanupSnapshotOnCartMerged;
use AIArmada\Cart\Features\Snapshots\EntryPoints\Listeners\SyncCartOnEvent;
use AIArmada\Cart\Features\Snapshots\Persistence\NormalizedCartSynchronizer;
use AIArmada\Cart\Features\Snapshots\Services\CartSyncManager;
use AIArmada\Cart\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(CartInstanceManager::class);
        $app->singleton(NormalizedCartSynchronizer::class);
        $app->singleton(CartSyncManager::class);
        $app->alias(CartSyncManager::class, CartSnapshotSyncInterface::class);
        $app->alias(CartSyncManager::class, 'cart.snapshot-sync');
    }

    public function boot(Application $app): void
    {
        if (! config('cart.events', true)) {
            return;
        }

        $dispatcher = $app->make(Dispatcher::class);
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
}
