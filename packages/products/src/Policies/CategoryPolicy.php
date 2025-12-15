<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\Category;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, Category $category): bool
    {
        if (method_exists($category, 'isOwnedBy')) {
            return $category->isOwnedBy($user);
        }

        return true;
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Category $category): bool
    {
        if (method_exists($category, 'isOwnedBy')) {
            return $category->isOwnedBy($user);
        }

        return true;
    }

    public function delete($user, Category $category): bool
    {
        // Prevent deletion if category has products
        if ($category->products()->exists()) {
            return false;
        }

        if (method_exists($category, 'isOwnedBy')) {
            return $category->isOwnedBy($user);
        }

        return true;
    }
}
