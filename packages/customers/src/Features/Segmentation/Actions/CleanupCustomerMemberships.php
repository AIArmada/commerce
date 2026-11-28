<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\Customers\Features\Segmentation\Api\CustomerMembershipCleaner;
use AIArmada\Customers\Models\Customer;

final readonly class CleanupCustomerMemberships implements CustomerMembershipCleaner
{
    public function __construct(
        private DetachDeletedSegmentMemberships $segments,
        private DetachDeletedCustomerGroupMemberships $groups,
    ) {}

    public function cleanup(string $customerId): void
    {
        $customer = Customer::query()->findOrFail($customerId);

        $customer->getConnection()->transaction(function () use ($customer): void {
            $this->segments->forCustomer($customer);
            $this->groups->forCustomer($customer);
        });
    }
}
