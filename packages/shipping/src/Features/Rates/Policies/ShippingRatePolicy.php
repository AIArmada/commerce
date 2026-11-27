<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Policies;

use AIArmada\Shipping\Features\Rates\Models\ShippingRate;
use AIArmada\Shipping\Features\Rates\Models\ShippingZone;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

final class ShippingRatePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', ShippingZone::class);
    }

    public function view(Authenticatable $user, ShippingRate $rate): bool
    {
        $zone = $rate->zone;

        return $zone instanceof ShippingZone && Gate::forUser($user)->allows('view', $zone);
    }

    public function create(Authenticatable $user): bool
    {
        return Gate::forUser($user)->allows('shipping.zones.manage-rates');
    }

    public function update(Authenticatable $user, ShippingRate $rate): bool
    {
        $zone = $rate->zone;

        return $zone instanceof ShippingZone && Gate::forUser($user)->allows('manageRates', $zone);
    }

    public function delete(Authenticatable $user, ShippingRate $rate): bool
    {
        return $this->update($user, $rate);
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->create($user);
    }
}
