<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class TransferCustomerGroupMemberships
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

        $source->getConnection()->transaction(function () use ($source, $target): void {
            $locked = Customer::query()->whereKey([$source->getKey(), $target->getKey()])->orderBy('id')->lockForUpdate()->get();

            if ($locked->count() !== 2) {
                throw new InvalidArgumentException('Both customers must remain accessible during the transfer.');
            }

            $source = $locked->find($source->getKey());
            $target = $locked->find($target->getKey());

            if (! $source instanceof Customer || ! $target instanceof Customer
                || $source->owner_type !== $target->owner_type || $source->owner_id !== $target->owner_id) {
                throw new InvalidArgumentException('Distinct persisted customers must share the same owner context.');
            }

            $attach = [];

            foreach ($source->groups()->get() as $group) {
                if ($group->owner_type !== $target->owner_type || $group->owner_id !== $target->owner_id) {
                    throw new InvalidArgumentException('Cannot transfer a group outside the target owner context.');
                }

                $attach[$group->getKey()] = [
                    'role' => $group->pivot->role ?? 'member',
                    'joined_at' => $group->pivot->joined_at ?? CarbonImmutable::now(),
                ];
            }

            if ($attach !== []) {
                $target->groups()->syncWithoutDetaching($attach);
            }
        });
    }
}
