<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

final class CategoryPolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    private function canAccessCategory(Category $category): bool
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $this->isGlobalModel($category);
        }

        if ($this->belongsToOwner($category, $owner)) {
            return true;
        }

        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        return $includeGlobal && $this->isGlobalModel($category);
    }

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, Category $category): bool
    {
        return $this->canAccessCategory($category);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, Category $category): bool
    {
        return $this->canAccessCategory($category);
    }

    public function delete(mixed $user, Category $category): bool
    {
        // Prevent deletion if category has products
        if ($category->products()->exists()) {
            return false;
        }

        return $this->canAccessCategory($category);
    }
}
