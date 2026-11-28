<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\EntryPoints\Policies;

use AIArmada\CommerceSupport\Policies\EnforcesOwnerScope;
use AIArmada\Customers\Features\Segmentation\Models\CustomerGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Throwable;

final class CustomerGroupPolicy
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
        return $this->hasPermission($user, 'customers.groups.view')
            && $this->ownerContextAllowsAccess(CustomerGroup::class);
    }

    public function view(mixed $user, CustomerGroup $group): bool
    {
        return $this->hasPermission($user, 'customers.groups.view')
            && $this->isViewableInOwnerScope($group);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, 'customers.groups.create')
            && $this->ownerContextAllowsAccess(CustomerGroup::class);
    }

    public function update(mixed $user, CustomerGroup $group): bool
    {
        return $this->hasPermission($user, 'customers.groups.update')
            && $this->isMutableInOwnerScope($group);
    }

    public function delete(mixed $user, CustomerGroup $group): bool
    {
        return $this->hasPermission($user, 'customers.groups.delete')
            && $this->isMutableInOwnerScope($group);
    }

    public function manageMembers(mixed $user, CustomerGroup $group): bool
    {
        return $this->hasPermission($user, 'customers.groups.manage-members')
            && $this->update($user, $group);
    }
}
