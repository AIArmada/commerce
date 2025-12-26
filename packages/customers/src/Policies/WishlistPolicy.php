<?php

declare(strict_types=1);

namespace AIArmada\Customers\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Wishlist;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class WishlistPolicy
{
    use HandlesAuthorization;

    private function isAuthenticated(mixed $user): bool
    {
        return $user !== null;
    }

    private function resolveOwner(): ?Model
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    private function isAccessible(Wishlist $wishlist): bool
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return true;
        }

        $owner = $this->resolveOwner();
        $includeGlobal = (bool) config('customers.features.owner.include_global', false);

        if ($owner === null) {
            return $wishlist->owner_type === null && $wishlist->owner_id === null;
        }

        if ($includeGlobal && $wishlist->isGlobal()) {
            return true;
        }

        return $wishlist->belongsToOwner($owner);
    }

    public function viewAny(mixed $user): bool
    {
        return $this->isAuthenticated($user);
    }

    public function view(mixed $user, Wishlist $wishlist): bool
    {
        return $this->isAuthenticated($user) && $this->isAccessible($wishlist);
    }

    public function create(mixed $user): bool
    {
        return $this->isAuthenticated($user);
    }

    public function update(mixed $user, Wishlist $wishlist): bool
    {
        return $this->isAuthenticated($user) && $this->isAccessible($wishlist);
    }

    public function delete(mixed $user, Wishlist $wishlist): bool
    {
        return $this->isAuthenticated($user) && $this->isAccessible($wishlist);
    }
}
