<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Illuminate\Contracts\Auth\Access\Authorizable;

class AffiliateFraudSignalPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return $user->can('affiliates.fraud.view-any');
    }

    public function view(Authorizable $user, AffiliateFraudSignal $signal): bool
    {
        return $user->can('affiliates.fraud.view');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('affiliates.fraud.create');
    }

    public function update(Authorizable $user, AffiliateFraudSignal $signal): bool
    {
        return $user->can('affiliates.fraud.update');
    }

    public function delete(Authorizable $user, AffiliateFraudSignal $signal): bool
    {
        return $user->can('affiliates.fraud.delete');
    }

    public function deleteAny(Authorizable $user): bool
    {
        return $user->can('affiliates.fraud.delete-any');
    }

    public function forceDelete(Authorizable $user, AffiliateFraudSignal $signal): bool
    {
        return $user->can('affiliates.fraud.force-delete');
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return $user->can('affiliates.fraud.force-delete-any');
    }

    public function restore(Authorizable $user, AffiliateFraudSignal $signal): bool
    {
        return $user->can('affiliates.fraud.restore');
    }

    public function restoreAny(Authorizable $user): bool
    {
        return $user->can('affiliates.fraud.restore-any');
    }

    public function reorder(Authorizable $user): bool
    {
        return $user->can('affiliates.fraud.reorder');
    }
}
