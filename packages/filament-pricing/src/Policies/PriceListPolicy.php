<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Policies;

use AIArmada\FilamentPricing\Policies\Concerns\HandlesPricingOwnerScoping;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PriceListPolicy
{
    use HandlesAuthorization;
    use HandlesPricingOwnerScoping;

    private function canAccessList(PriceList $priceList): bool
    {
        return $this->canAccess($priceList);
    }

    /**
     * Determine whether the user can view any price lists.
     */
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the price list.
     */
    public function view(mixed $user, PriceList $priceList): bool
    {
        return $this->canAccessList($priceList);
    }

    /**
     * Determine whether the user can create price lists.
     */
    public function create(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the price list.
     */
    public function update(mixed $user, PriceList $priceList): bool
    {
        return $this->canAccessList($priceList);
    }

    /**
     * Determine whether the user can update any price lists.
     */
    public function updateAny(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can delete the price list.
     */
    public function delete(mixed $user, PriceList $priceList): bool
    {
        return $this->canAccessList($priceList);
    }

    /**
     * Determine whether the user can duplicate the price list.
     */
    public function duplicate(mixed $user, PriceList $priceList): bool
    {
        return $this->view($user, $priceList);
    }
}
