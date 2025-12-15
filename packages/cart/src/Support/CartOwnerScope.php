<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Database\Query\Builder;

final class CartOwnerScope
{
    public static function apply(Builder $query, StorageInterface $storage): Builder
    {
        $ownerType = $storage->getOwnerType();
        $ownerId = $storage->getOwnerId();

        if ($ownerType !== null && $ownerId !== null) {
            return $query->where('owner_type', $ownerType)
                ->where('owner_id', (string) $ownerId);
        }

        return $query->where('owner_type', '')
            ->where('owner_id', '');
    }
}
