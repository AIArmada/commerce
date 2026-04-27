<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Database\Query\Builder;

final class CartOwnerScope
{
    public static function apply(Builder $query, StorageInterface $storage): Builder
    {
        return self::applyForOwner($query, $storage->getOwnerType(), $storage->getOwnerId());
    }

    public static function applyForOwner(Builder $query, ?string $ownerType, string | int | null $ownerId): Builder
    {
        return OwnerQuery::applyToQueryBuilder(
            $query,
            OwnerContext::fromTypeAndId($ownerType, $ownerId),
        );
    }
}
