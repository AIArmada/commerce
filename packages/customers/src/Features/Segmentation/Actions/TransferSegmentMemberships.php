<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Customer;
use InvalidArgumentException;

final class TransferSegmentMemberships
{
    public function execute(Customer $source, Customer $target): void
    {
        if (! $source->exists || ! $target->exists || $source->is($target)
            || $source->owner_type !== $target->owner_type || $source->owner_id !== $target->owner_id) {
            throw new InvalidArgumentException('Distinct persisted customers must share the same owner context.');
        }

        if (Customer::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Customer::class, $source->getKey());
            OwnerWriteGuard::findOrFailForOwner(Customer::class, $target->getKey());
        }

        $relation = $source->segments();
        $ids = $relation->pluck($relation->getRelated()->qualifyColumn($relation->getRelatedKeyName()))->all();

        if ($ids !== []) {
            $target->segments()->syncWithoutDetaching($ids);
        }
    }
}
