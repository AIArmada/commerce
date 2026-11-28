<?php

declare(strict_types=1);

namespace AIArmada\Customers\Policies;

use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\CommerceSupport\Policies\EnforcesOwnerScope;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Throwable;

final class AddressPolicy
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
        return $this->hasPermission($user, 'customers.addresses.view')
            && $this->ownerContextAllowsAccess(Address::class);
    }

    public function view(mixed $user, Address $address): bool
    {
        return $this->hasPermission($user, 'customers.addresses.view')
            && $this->isViewableInOwnerScope($address);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, 'customers.addresses.create')
            && $this->ownerContextAllowsAccess(Address::class);
    }

    public function update(mixed $user, Address $address): bool
    {
        return $this->hasPermission($user, 'customers.addresses.update')
            && $this->isMutableInOwnerScope($address);
    }

    public function delete(mixed $user, Address $address): bool
    {
        return $this->hasPermission($user, 'customers.addresses.delete')
            && $this->isMutableInOwnerScope($address);
    }
}
