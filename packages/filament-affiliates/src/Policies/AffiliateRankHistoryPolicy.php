<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class AffiliateRankHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank-history.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank-history.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank-history.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank-history.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-rank-history.delete');
    }
}
