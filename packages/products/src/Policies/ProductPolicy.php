<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any products.
     */
    public function viewAny($user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view($user, Product $product): bool
    {
        // Check ownership if the product has an owner
        if (method_exists($product, 'isOwnedBy')) {
            return $product->isOwnedBy($user);
        }

        return true;
    }

    /**
     * Determine whether the user can create products.
     */
    public function create($user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update($user, Product $product): bool
    {
        if (method_exists($product, 'isOwnedBy')) {
            return $product->isOwnedBy($user);
        }

        return true;
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete($user, Product $product): bool
    {
        if (method_exists($product, 'isOwnedBy')) {
            return $product->isOwnedBy($user);
        }

        return true;
    }

    /**
     * Determine whether the user can duplicate the product.
     */
    public function duplicate($user, Product $product): bool
    {
        return $this->view($user, $product);
    }
}
