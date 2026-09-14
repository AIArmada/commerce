<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Policies;

use AIArmada\FilamentPricing\Policies\Concerns\HandlesPricingOwnerScoping;
use AIArmada\Pricing\Models\Price;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PricePolicy
{
    use HandlesAuthorization;
    use HandlesPricingOwnerScoping;

    private function canAccessPrice(Price $price): bool
    {
        return $this->canAccess($price);
    }

    /**
     * Determine whether the user can view any prices.
     */
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the price.
     */
    public function view(mixed $user, Price $price): bool
    {
        return $this->canAccessPrice($price);
    }

    /**
     * Determine whether the user can create prices.
     */
    public function create(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the price.
     */
    public function update(mixed $user, Price $price): bool
    {
        return $this->canAccessPrice($price);
    }

    /**
     * Determine whether the user can update any prices.
     */
    public function updateAny(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can delete the price.
     */
    public function delete(mixed $user, Price $price): bool
    {
        return $this->canAccessPrice($price);
    }

    /**
     * Determine whether the user can duplicate the price.
     */
    public function duplicate(mixed $user, Price $price): bool
    {
        return $this->view($user, $price);
    }
}
