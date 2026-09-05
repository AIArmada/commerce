<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Support;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class NetworkAdminAccess
{
    public static function allows(?Authorizable $user = null): bool
    {
        $ability = config('filament-affiliate-network.authorization.admin_ability');

        if (! is_string($ability) || $ability === '') {
            return false;
        }

        return $user instanceof Authorizable
            ? $user->can($ability)
            : FilamentPermission::hasAbility($ability);
    }
}
