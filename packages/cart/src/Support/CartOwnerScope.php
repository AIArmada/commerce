<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

use AIArmada\Cart\Models\CartModel;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Illuminate\Database\Query\Builder;

final class CartOwnerScope
{
    public static function apply(Builder $query, StorageInterface $storage): Builder
    {
        return self::applyForOwner($query, $storage->getOwnerType(), $storage->getOwnerId());
    }

    public static function applyForOwner(Builder $query, ?string $ownerType, string | int | null $ownerId): Builder
    {
        $columns = OwnerTupleColumns::forModelClass(CartModel::class);
        $owner = OwnerTupleParser::fromTypeAndId($ownerType, $ownerId)->toOwnerModel();

        return OwnerQuery::applyToQueryBuilder(
            $query,
            $owner,
            ownerTypeColumn: $columns->ownerTypeColumn,
            ownerIdColumn: $columns->ownerIdColumn,
        );
    }
}
