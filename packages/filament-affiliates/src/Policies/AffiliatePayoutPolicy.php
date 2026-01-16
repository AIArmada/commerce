<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Contracts\Auth\Access\Authorizable;

class AffiliatePayoutPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return $user->can('affiliates.payout.view-any');
    }

    public function view(Authorizable $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.view');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('affiliates.payout.create');
    }

    public function update(Authorizable $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.update');
    }

    public function delete(Authorizable $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.delete');
    }

    public function deleteAny(Authorizable $user): bool
    {
        return $user->can('affiliates.payout.delete-any');
    }

    public function forceDelete(Authorizable $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.force-delete');
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return $user->can('affiliates.payout.force-delete-any');
    }

    public function restore(Authorizable $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.restore');
    }

    public function restoreAny(Authorizable $user): bool
    {
        return $user->can('affiliates.payout.restore-any');
    }

    public function reorder(Authorizable $user): bool
    {
        return $user->can('affiliates.payout.reorder');
    }

    public function export(Authorizable $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.export');
    }
}
