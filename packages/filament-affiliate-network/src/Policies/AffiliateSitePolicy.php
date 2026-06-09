<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Policies;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class AffiliateSitePolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return true;
    }

    public function view(Authorizable $user, AffiliateSite $site): bool
    {
        return true;
    }

    public function create(Authorizable $user): bool
    {
        return true;
    }

    public function update(Authorizable $user, AffiliateSite $site): bool
    {
        return true;
    }

    public function delete(Authorizable $user, AffiliateSite $site): bool
    {
        return true;
    }

    public function deleteAny(Authorizable $user): bool
    {
        return true;
    }

    public function restore(Authorizable $user, AffiliateSite $site): bool
    {
        return true;
    }

    public function forceDelete(Authorizable $user, AffiliateSite $site): bool
    {
        return true;
    }
}
