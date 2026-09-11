<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\Category;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class CategoryPolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    private function canAccessCategory(Category $category): bool
    {
        return $this->canAccess($category);
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
