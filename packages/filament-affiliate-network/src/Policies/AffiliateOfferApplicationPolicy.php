<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Policies;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class AffiliateOfferApplicationPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return true;
    }

    public function view(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return true;
    }

    public function create(Authorizable $user): bool
    {
        return true;
    }

    public function update(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return true;
    }

    public function delete(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return true;
    }

    public function deleteAny(Authorizable $user): bool
    {
        return true;
    }

    public function restore(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return true;
    }

    public function forceDelete(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return true;
    }
}
