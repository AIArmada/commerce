<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class AffiliateRankPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank.delete');
    }
}
