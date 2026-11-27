<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Services;

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Features\Snapshots\EntryPoints\Jobs\SyncNormalizedCartJob;
use AIArmada\Cart\Features\Snapshots\Persistence\NormalizedCartSynchronizer;
use AIArmada\Cart\Runtime\CartInstanceManager;
use AIArmada\Cart\Runtime\CartOwnerIdentity;
use AIArmada\Cart\Runtime\CartSnapshotSyncInterface;

class CartSyncManager implements CartSnapshotSyncInterface
{
    public function __construct(
        private NormalizedCartSynchronizer $synchronizer,
        private CartInstanceManager $cartInstances,
    ) {}

    public function sync(BaseCart $cart, bool $force = false): void
    {
        $cart = $this->cartInstances->prepare($cart);

        if (! $force && config('cart.snapshots.synchronization.queue_sync', true)) {
            $storage = $cart->storage();
            SyncNormalizedCartJob::dispatch(
                identifier: $cart->getIdentifier(),
                instance: $cart->instance(),
                ownerType: $storage->getOwnerType(),
                ownerId: $storage->getOwnerId(),
                ownerIsGlobal: $storage->getOwnerType() === null && $storage->getOwnerId() === null,
            );

            return;
        }

        $this->synchronizer->syncFromCart($cart);
    }

    public function syncByIdentity(string $instance, string $identifier): void
    {
        $cart = $this->cartInstances->resolve($instance, $identifier);
        $this->sync($cart, force: true);
    }

    public function deleteByIdentity(
        string $instance,
        string $identifier,
        ?CartOwnerIdentity $identity = null,
    ): void {
        $this->synchronizer->deleteNormalizedCart($identifier, $instance, $identity);
    }
}
