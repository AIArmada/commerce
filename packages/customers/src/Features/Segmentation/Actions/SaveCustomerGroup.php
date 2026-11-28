<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Segmentation\Models\CustomerGroup;
use InvalidArgumentException;
use RuntimeException;

final readonly class SaveCustomerGroup
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CustomerGroup
    {
        $group = new CustomerGroup;

        return $group->getConnection()->transaction(function () use ($group, $attributes): CustomerGroup {
            $group->fill($attributes);

            if (! $group->save()) {
                throw new RuntimeException('Customer group persistence was cancelled.');
            }

            return $group;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(CustomerGroup $group, array $attributes): CustomerGroup
    {
        if (CustomerGroup::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CustomerGroup::class, $group->getKey());
        }

        return $group->getConnection()->transaction(function () use ($group, $attributes): CustomerGroup {
            $locked = $group->newQuery()->whereKey($group->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill($attributes);

            if (! $locked->save()) {
                throw new RuntimeException('Customer group persistence was cancelled.');
            }

            return $locked;
        });
    }

    public function delete(CustomerGroup $group): bool
    {
        if (! $group->exists || $group->getKey() === null) {
            throw new InvalidArgumentException('Customer group must be persisted.');
        }

        return $group->getConnection()->transaction(function () use ($group): bool {
            if (CustomerGroup::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CustomerGroup::class, $group->getKey());
            }

            $locked = $group->newQuery()->whereKey($group->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->delete()) {
                throw new RuntimeException('Customer group deletion was cancelled.');
            }

            return true;
        });
    }
}
