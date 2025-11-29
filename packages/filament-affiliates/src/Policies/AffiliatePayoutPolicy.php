<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Foundation\Auth\User;

class AffiliatePayoutPolicy
{
    public function update(User $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.update');
    }

    public function export(User $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.export');
    }
}
