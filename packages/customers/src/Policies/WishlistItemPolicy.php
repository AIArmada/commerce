<?php

declare(strict_types=1);

namespace AIArmada\Customers\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\WishlistItem;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class WishlistItemPolicy
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

    private function isAccessible(WishlistItem $item): bool
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return true;
        }

        $owner = $this->resolveOwner();
        $includeGlobal = (bool) config('customers.features.owner.include_global', false);

        if ($owner === null) {
            return $item->owner_type === null && $item->owner_id === null;
        }

        if ($includeGlobal && $item->isGlobal()) {
            return true;
        }

        return $item->belongsToOwner($owner);
    }

    public function viewAny(mixed $user): bool
    {
        return $this->isAuthenticated($user);
    }

    public function view(mixed $user, WishlistItem $wishlistItem): bool
    {
        return $this->isAuthenticated($user) && $this->isAccessible($wishlistItem);
    }

    public function create(mixed $user): bool
    {
        return $this->isAuthenticated($user);
    }

    public function update(mixed $user, WishlistItem $wishlistItem): bool
    {
        return $this->isAuthenticated($user) && $this->isAccessible($wishlistItem);
    }

    public function delete(mixed $user, WishlistItem $wishlistItem): bool
    {
        return $this->isAuthenticated($user) && $this->isAccessible($wishlistItem);
    }
}
