<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use AIArmada\Cart\Data\CartIdentity;

interface CartIdentityLookup
{
    public function findSnapshotById(string $uuid): ?CartIdentity;
}
