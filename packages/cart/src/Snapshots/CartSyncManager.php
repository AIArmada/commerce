<?php

declare(strict_types=1);

namespace AIArmada\Cart\Snapshots;

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Contracts\CartSnapshotSyncInterface;

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
        ?string $ownerType = null,
        string | int | null $ownerId = null,
    ): void {
        $this->synchronizer->deleteNormalizedCart($identifier, $instance, $ownerType, $ownerId);
    }
}
