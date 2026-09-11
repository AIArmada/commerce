<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ProductPolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    private function canAccessProduct(Product $product): bool
    {
        return $this->canAccess($product);
    }

    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(mixed $user, Product $product): bool
    {
        return $this->canAccessProduct($product);
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(mixed $user, Product $product): bool
    {
        return $this->canAccessProduct($product);
    }

    /**
     * Determine whether the user can update any products.
     */
    public function updateAny(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(mixed $user, Product $product): bool
    {
        return $this->canAccessProduct($product);
    }

    /**
     * Determine whether the user can duplicate the product.
     */
    public function duplicate(mixed $user, Product $product): bool
    {
        return $this->view($user, $product);
    }
}
