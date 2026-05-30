<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Contracts\Auth\Access\Authorizable;

class AffiliateConversionPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return $user->can('affiliate_conversion.viewAny') || $user->can('affiliate.viewAny');
    }

    public function view(Authorizable $user, AffiliateConversion $conversion): bool
    {
        return $user->can('affiliate_conversion.view') || $user->can('affiliate.view');
    }

    public function update(Authorizable $user, AffiliateConversion $conversion): bool
    {
        return $user->can('affiliate_conversion.update') || $user->can('affiliate.approve');
    }
}
