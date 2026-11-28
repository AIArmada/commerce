<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity\EntryPoints\Policies;

use AIArmada\CommerceSupport\Policies\EnforcesOwnerScope;
use AIArmada\Customers\Features\Identity\Models\CustomerNote;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Throwable;

final class CustomerNotePolicy
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
        return $this->hasPermission($user, 'customers.notes.view')
            && $this->ownerContextAllowsAccess(CustomerNote::class);
    }

    public function view(mixed $user, CustomerNote $note): bool
    {
        return $this->hasPermission($user, 'customers.notes.view')
            && $this->isViewableInOwnerScope($note);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, 'customers.notes.create')
            && $this->ownerContextAllowsAccess(CustomerNote::class);
    }

    public function update(mixed $user, CustomerNote $note): bool
    {
        return $this->hasPermission($user, 'customers.notes.update')
            && $this->isMutableInOwnerScope($note);
    }

    public function delete(mixed $user, CustomerNote $note): bool
    {
        return $this->hasPermission($user, 'customers.notes.delete')
            && $this->isMutableInOwnerScope($note);
    }
}
