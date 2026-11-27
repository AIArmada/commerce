<?php

declare(strict_types=1);

namespace AIArmada\Links\Persistence;

use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Links\Models\Link;

final readonly class LockOwnedLink
{
    public function handle(Link $link): Link
    {
        return OwnerScopeOverride::withoutIncludeGlobal(
            static fn (): Link => $link->newQuery()->whereKey($link->getKey())->lockForUpdate()->firstOrFail(),
        );
    }
}
