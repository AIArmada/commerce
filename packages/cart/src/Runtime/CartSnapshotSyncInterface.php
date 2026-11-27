<?php

declare(strict_types=1);

namespace AIArmada\Cart\Runtime;

use AIArmada\Cart\Cart;

interface CartSnapshotSyncInterface
{
    public function sync(Cart $cart, bool $force = false): void;

    public function syncByIdentity(string $instance, string $identifier): void;

    public function deleteByIdentity(
        string $instance,
        string $identifier,
        ?CartOwnerIdentity $identity = null,
    ): void;
}
