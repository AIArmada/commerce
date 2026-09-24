<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use Illuminate\Foundation\Auth\User;

final class AffiliateTouchpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('affiliate-touchpoint.viewAny') || $user->can('affiliate.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->can('affiliate-touchpoint.view') || $user->can('affiliate.view');
    }

    public function create(User $user): bool
    {
        return $user->can('affiliate-touchpoint.create') || $user->can('affiliate.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->can('affiliate-touchpoint.update') || $user->can('affiliate.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->can('affiliate-touchpoint.delete') || $user->can('affiliate.delete');
    }
}
