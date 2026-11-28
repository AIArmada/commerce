<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Customer;
use InvalidArgumentException;
use RuntimeException;

final readonly class DeleteCustomer
{
    public function execute(Customer $customer): bool
    {
        if (! $customer->exists || $customer->getKey() === null) {
            throw new InvalidArgumentException('Customer must be persisted.');
        }

        return $customer->getConnection()->transaction(function () use ($customer): bool {
            if (Customer::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Customer::class, $customer->getKey());
            }

            $locked = $customer->newQuery()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->delete()) {
                throw new RuntimeException('Customer deletion was cancelled.');
            }

            return true;
        });
    }
}
