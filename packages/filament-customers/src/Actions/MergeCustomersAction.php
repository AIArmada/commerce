<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Actions;

use AIArmada\Customers\Actions\MergeCustomers;
use AIArmada\Customers\Models\Customer;

final class MergeCustomersAction
{
    public function __construct(
        private readonly MergeCustomers $mergeCustomers,
    ) {}

    public function execute(Customer $target, Customer $source): Customer
    {
        return $this->mergeCustomers->execute($target, $source);
    }
}
