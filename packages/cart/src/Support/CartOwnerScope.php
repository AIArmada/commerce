<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Database\Query\Builder;

final class CartOwnerScope
{
    public static function apply(Builder $query, StorageInterface $storage): Builder
    {
        return self::applyForOwner($query, $storage->getOwnerType(), $storage->getOwnerId());
    }

    public static function applyForOwner(Builder $query, ?string $ownerType, string | int | null $ownerId): Builder
    {
        if ($ownerType !== null && $ownerId !== null && $ownerType !== '' && (string) $ownerId !== '') {
            return $query->where('owner_type', $ownerType)
                ->where('owner_id', (string) $ownerId);
        }

        return $query
            ->where(function (Builder $builder): void {
                $builder->whereNull('owner_type')
                    ->orWhere('owner_type', '');
            })
            ->where(function (Builder $builder): void {
                $builder->whereNull('owner_id')
                    ->orWhere('owner_id', '');
            });
    }
}
