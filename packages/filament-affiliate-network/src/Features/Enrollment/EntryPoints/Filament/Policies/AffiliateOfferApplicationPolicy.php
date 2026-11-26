<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Enrollment\EntryPoints\Filament\Policies;

use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\FilamentAffiliateNetwork\Platform\NetworkAdminAccess;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class AffiliateOfferApplicationPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function view(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function create(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function update(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function delete(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function restore(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function forceDelete(Authorizable $user, AffiliateOfferApplication $application): bool
    {
        return NetworkAdminAccess::allows($user);
    }
}
