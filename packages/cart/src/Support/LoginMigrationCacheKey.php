<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

final class LoginMigrationCacheKey
{
    public static function make(string $identifier): string
    {
        $logicalKey = 'cart.migration.' . hash('sha256', mb_strtolower(mb_trim($identifier)));

        return OwnerCache::key(self::resolveOwner(), $logicalKey);
    }

    private static function resolveOwner(): ?Model
    {
        if (! (bool) config('cart.owner.enabled', false)) {
            return null;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Cart login migration cache access requires an owner context or explicit global context.',
        );

        return $owner;
    }
}
