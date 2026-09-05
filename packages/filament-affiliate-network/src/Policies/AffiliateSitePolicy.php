<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Policies;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class AffiliateSitePolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function view(Authorizable $user, AffiliateSite $site): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function create(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function update(Authorizable $user, AffiliateSite $site): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function delete(Authorizable $user, AffiliateSite $site): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function restore(Authorizable $user, AffiliateSite $site): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function forceDelete(Authorizable $user, AffiliateSite $site): bool
    {
        return NetworkAdminAccess::allows($user);
    }
}
