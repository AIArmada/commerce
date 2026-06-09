<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Actions;

use AIArmada\Customers\Events\CustomerUpdated;
use AIArmada\Customers\Models\Customer;

final class MergeCustomersAction
{
    /**
     * Merge $source customer into $target customer.
     * Transfers all addresses, notes, and related records,
     * then deletes $source.
     */
    public function execute(Customer $target, Customer $source): Customer
    {
        $target->load('addresses', 'notes');

        $source->addresses()->update(['customer_id' => $target->id]);
        $source->notes()->update(['customer_id' => $target->id]);

        if ($source->email && ! $target->email) {
            $target->email = $source->email;
        }

        if ($source->phone && ! $target->phone) {
            $target->phone = $source->phone;
        }

        $target->save();

        event(new CustomerUpdated($target));

        $source->delete();

        return $target;
    }
}
