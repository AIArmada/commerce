<?php

declare(strict_types=1);

namespace AIArmada\Customers\Policies;

use AIArmada\Customers\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, Customer $customer): bool
    {
        if (method_exists($customer, 'isOwnedBy')) {
            return $customer->isOwnedBy($user);
        }

        // User can view their own customer record
        if ($customer->user_id === $user->id) {
            return true;
        }

        return true;
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Customer $customer): bool
    {
        if (method_exists($customer, 'isOwnedBy')) {
            return $customer->isOwnedBy($user);
        }

        return true;
    }

    public function delete($user, Customer $customer): bool
    {
        // Cannot delete customers with orders
        // This would integrate with orders package
        if (method_exists($customer, 'isOwnedBy')) {
            return $customer->isOwnedBy($user);
        }

        return true;
    }

    /**
     * Determine if user can add credit to customer wallet.
     */
    public function addCredit($user, Customer $customer): bool
    {
        if (method_exists($customer, 'isOwnedBy')) {
            return $customer->isOwnedBy($user);
        }

        return true;
    }

    /**
     * Determine if user can deduct credit from customer wallet.
     */
    public function deductCredit($user, Customer $customer): bool
    {
        if (method_exists($customer, 'isOwnedBy')) {
            return $customer->isOwnedBy($user);
        }

        return true;
    }
}
