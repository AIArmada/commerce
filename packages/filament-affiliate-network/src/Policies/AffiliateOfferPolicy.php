<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Policies;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class AffiliateOfferPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return true;
    }

    public function view(Authorizable $user, AffiliateOffer $offer): bool
    {
        return true;
    }

    public function create(Authorizable $user): bool
    {
        return true;
    }

    public function update(Authorizable $user, AffiliateOffer $offer): bool
    {
        return true;
    }

    public function delete(Authorizable $user, AffiliateOffer $offer): bool
    {
        return true;
    }

    public function deleteAny(Authorizable $user): bool
    {
        return true;
    }

    public function restore(Authorizable $user, AffiliateOffer $offer): bool
    {
        return true;
    }

    public function forceDelete(Authorizable $user, AffiliateOffer $offer): bool
    {
        return true;
    }
}
