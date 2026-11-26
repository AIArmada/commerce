<?php

declare(strict_types=1);

namespace AIArmada\Customers\Policies;

use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class AddressPolicy
{
    use HandlesAuthorization;

    private function hasPermission(mixed $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        try {
            // Gate-mediated check first: Spatie permissions resolve through
            // Gate::before, which also preserves Super Admin overrides.
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

    private function resolveOwner(): ?Model
    {
        $config = Address::ownerScopeConfig();

        if (! $config->enabled) {
            return null;
        }

        return OwnerContext::resolveConfigured($config);
    }

    private function isViewable(Address $address): bool
    {
        $config = Address::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        $owner = $this->resolveOwner();

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $address->isGlobal();
        }

        if ($address->belongsToOwner($owner)) {
            return true;
        }

        return $config->includeGlobal && $address->isGlobal();
    }

    private function isMutable(Address $address): bool
    {
        $config = Address::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        $owner = $this->resolveOwner();

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $address->isGlobal();
        }

        return $address->belongsToOwner($owner);
    }

    public function viewAny(mixed $user): bool
    {
        return $this->hasPermission($user, 'customers.addresses.view');
    }

    public function view(mixed $user, Address $address): bool
    {
        return $this->hasPermission($user, 'customers.addresses.view') && $this->isViewable($address);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, 'customers.addresses.create');
    }

    public function update(mixed $user, Address $address): bool
    {
        return $this->hasPermission($user, 'customers.addresses.update') && $this->isMutable($address);
    }

    public function delete(mixed $user, Address $address): bool
    {
        return $this->hasPermission($user, 'customers.addresses.delete') && $this->isMutable($address);
    }
}
