<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\EntryPoints\Policies;

use AIArmada\CommerceSupport\Policies\EnforcesOwnerScope;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Throwable;

final class SegmentPolicy
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
        return $this->hasPermission($user, 'customers.segments.view')
            && $this->ownerContextAllowsAccess(Segment::class);
    }

    public function view(mixed $user, Segment $segment): bool
    {
        return $this->hasPermission($user, 'customers.segments.view')
            && $this->isViewableInOwnerScope($segment);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, 'customers.segments.create')
            && $this->ownerContextAllowsAccess(Segment::class);
    }

    public function update(mixed $user, Segment $segment): bool
    {
        return $this->hasPermission($user, 'customers.segments.update')
            && $this->isMutableInOwnerScope($segment);
    }

    public function delete(mixed $user, Segment $segment): bool
    {
        return $this->hasPermission($user, 'customers.segments.delete')
            && $this->isMutableInOwnerScope($segment);
    }

    public function rebuild(mixed $user, Segment $segment): bool
    {
        return $this->hasPermission($user, 'customers.segments.rebuild')
            && $this->isMutableInOwnerScope($segment)
            && $segment->is_automatic
            && $segment->isActive();
    }
}
