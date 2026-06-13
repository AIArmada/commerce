<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Actions;

use AIArmada\Customers\Events\CustomerUpdated;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Services\CustomerResolver;

final class MergeCustomersAction
{
    public function __construct(
        private readonly CustomerResolver $customerResolver,
    ) {}

    public function execute(Customer $target, Customer $source): Customer
    {
        $mergedCustomer = $this->customerResolver->mergeCustomers($source, $target);

        event(new CustomerUpdated($mergedCustomer));

        return $mergedCustomer;
    }
}
