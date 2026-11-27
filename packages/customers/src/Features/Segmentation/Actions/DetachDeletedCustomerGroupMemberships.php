<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;

final readonly class DetachDeletedCustomerGroupMemberships
{
    public function forCustomer(Customer $customer): void
    {
        if (Customer::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Customer::class, $customer->getKey());
        }

        $customer->groups()->detach();
    }

    public function forGroup(CustomerGroup $group): void
    {
        if (CustomerGroup::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CustomerGroup::class, $group->getKey());
        }

        $group->members()->detach();
    }
}
