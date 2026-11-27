<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Segmentation\Models\CustomerGroup;

final readonly class SaveCustomerGroup
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CustomerGroup
    {
        $group = new CustomerGroup;

        return $group->getConnection()->transaction(fn (): CustomerGroup => $group->newQuery()->create($attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(CustomerGroup $group, array $attributes): CustomerGroup
    {
        if (CustomerGroup::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CustomerGroup::class, $group->getKey());
        }

        return $group->getConnection()->transaction(function () use ($group, $attributes): CustomerGroup {
            $locked = $group->newQuery()->whereKey($group->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill($attributes)->save();

            return $locked;
        });
    }

    public function delete(CustomerGroup $group): bool
    {
        if (CustomerGroup::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CustomerGroup::class, $group->getKey());
        }

        return $group->getConnection()->transaction(fn (): bool => (bool) $group->newQuery()->whereKey($group->getKey())->lockForUpdate()->firstOrFail()->delete());
    }
}
