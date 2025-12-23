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

        if ($includeGlobal && method_exists($item, 'isGlobal') && $item->isGlobal()) {
            return true;
        }

        if (method_exists($item, 'belongsToOwner')) {
            return $item->belongsToOwner($owner);
        }

        return $item->owner_type === $owner->getMorphClass()
            && $item->owner_id === $owner->getKey();
    }

    public function viewAny($user): bool
    {
        return $this->isAuthenticated($user);
    }

    public function view($user, WishlistItem $item): bool
    {
        return $this->isAuthenticated($user) && $this->isAccessible($item);
    }

    public function create($user): bool
    {
        return $this->isAuthenticated($user);
    }

    public function update($user, WishlistItem $item): bool
    {
        return $this->isAuthenticated($user) && $this->isAccessible($item);
    }

    public function delete($user, WishlistItem $item): bool
    {
        return $this->isAuthenticated($user) && $this->isAccessible($item);
    }
}
