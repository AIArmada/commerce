<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Policies;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class AffiliateOfferPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function view(Authorizable $user, AffiliateOffer $offer): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function create(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function update(Authorizable $user, AffiliateOffer $offer): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function delete(Authorizable $user, AffiliateOffer $offer): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function restore(Authorizable $user, AffiliateOffer $offer): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function forceDelete(Authorizable $user, AffiliateOffer $offer): bool
    {
        return NetworkAdminAccess::allows($user);
    }
}
