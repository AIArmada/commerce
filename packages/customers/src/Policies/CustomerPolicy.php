<?php

declare(strict_types=1);

namespace AIArmada\Customers\Policies;

use AIArmada\CommerceSupport\Policies\EnforcesOwnerScope;
use AIArmada\Customers\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Throwable;

final class CustomerPolicy
{
    use EnforcesOwnerScope;
    use HandlesAuthorization;

    private function hasPermission(mixed $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        try {
            if ($user instanceof Authorizable) {
                return $user->can($permission);
            }

            if (is_object($user) && method_exists($user, 'hasPermissionTo')) {
                return (bool) $user->hasPermissionTo($permission);
            }

            if (is_object($user) && method_exists($user, 'can')) {
                return (bool) $user->can($permission);
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    public function viewAny(mixed $user): bool
    {
        return $this->hasPermission($user, 'customers.customers.view')
            && $this->ownerContextAllowsAccess(Customer::class);
    }

    public function view(mixed $user, Customer $customer): bool
    {
        return $this->hasPermission($user, 'customers.customers.view')
            && $this->isViewableInOwnerScope($customer);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, 'customers.customers.create')
            && $this->ownerContextAllowsAccess(Customer::class);
    }

    public function update(mixed $user, Customer $customer): bool
    {
        return $this->hasPermission($user, 'customers.customers.update')
            && $this->isMutableInOwnerScope($customer);
    }

    public function delete(mixed $user, Customer $customer): bool
    {
        return $this->hasPermission($user, 'customers.customers.delete')
            && $this->isMutableInOwnerScope($customer);
    }

    public function addCredit(mixed $user, Customer $customer): bool
    {
        return $this->hasPermission($user, 'customers.customers.add-credit')
            && $this->isMutableInOwnerScope($customer);
    }

    public function deductCredit(mixed $user, Customer $customer): bool
    {
        return $this->hasPermission($user, 'customers.customers.deduct-credit')
            && $this->isMutableInOwnerScope($customer);
    }
}
