<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use AIArmada\Cart\Cart;

interface CartSnapshotSyncInterface
{
    public function sync(Cart $cart, bool $force = false): void;

    public function syncByIdentity(string $instance, string $identifier): void;

    public function deleteByIdentity(
        string $instance,
        string $identifier,
        ?string $ownerType = null,
        string | int | null $ownerId = null,
    ): void;
}
