<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use Illuminate\Foundation\Auth\User;

final class AffiliateLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('affiliate-link.viewAny') || $user->can('affiliate.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->can('affiliate-link.view') || $user->can('affiliate.view');
    }

    public function create(User $user): bool
    {
        return $user->can('affiliate-link.create') || $user->can('affiliate.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->can('affiliate-link.update') || $user->can('affiliate.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->can('affiliate-link.delete') || $user->can('affiliate.delete');
    }
}
