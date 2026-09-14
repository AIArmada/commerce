<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Policies;

use AIArmada\FilamentPricing\Policies\Concerns\HandlesPricingOwnerScoping;
use AIArmada\Pricing\Models\PriceTier;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PriceTierPolicy
{
    use HandlesAuthorization;
    use HandlesPricingOwnerScoping;

    private function canAccessTier(PriceTier $tier): bool
    {
        return $this->canAccess($tier);
    }

    /**
     * Determine whether the user can view any price tiers.
     */
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the price tier.
     */
    public function view(mixed $user, PriceTier $tier): bool
    {
        return $this->canAccessTier($tier);
    }

    /**
     * Determine whether the user can create price tiers.
     */
    public function create(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the price tier.
     */
    public function update(mixed $user, PriceTier $tier): bool
    {
        return $this->canAccessTier($tier);
    }

    /**
     * Determine whether the user can update any price tiers.
     */
    public function updateAny(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can delete the price tier.
     */
    public function delete(mixed $user, PriceTier $tier): bool
    {
        return $this->canAccessTier($tier);
    }

    /**
     * Determine whether the user can duplicate the price tier.
     */
    public function duplicate(mixed $user, PriceTier $tier): bool
    {
        return $this->view($user, $tier);
    }
}
