<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Policies;

use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\FilamentAffiliateNetwork\Platform\NetworkAdminAccess;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class MerchantStatementPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function view(Authorizable $user, MerchantStatement $statement): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function create(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function update(Authorizable $user, MerchantStatement $statement): bool
    {
        return NetworkAdminAccess::allows($user);
    }

    public function delete(Authorizable $user, MerchantStatement $statement): bool
    {
        return NetworkAdminAccess::allows($user) && $statement->status->value === 'draft';
    }

    public function deleteAny(Authorizable $user): bool
    {
        return NetworkAdminAccess::allows($user);
    }
}
