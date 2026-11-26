<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Policies;

use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\FilamentAffiliateNetwork\Platform\NetworkAdminAccess;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class CreatorPostbackPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function view(Authorizable $user, CreatorPostbackEndpoint $endpoint): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function create(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function update(Authorizable $user, CreatorPostbackEndpoint $endpoint): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function delete(Authorizable $user, CreatorPostbackEndpoint $endpoint): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }
}
