<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Actions;

use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

final readonly class ClearLiveCart
{
    public function __construct(private CartInstanceManager $cartInstances) {}

    public function execute(CartSnapshot $snapshot): void
    {
        if (CartSnapshot::ownerScopingEnabled()) {
            /** @var CartSnapshot $snapshot */
            $snapshot = OwnerWriteGuard::findOrFailForOwner(
                CartSnapshot::class,
                (string) $snapshot->getKey(),
                includeGlobal: false,
                message: 'Cart is not accessible in the current owner scope.',
            );
        }

        $this->cartInstances->resolveForSnapshot($snapshot->toSnapshotData())->clear();
    }
}
