<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class AffiliateProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('affiliate-program.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-program.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('affiliate-program.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-program.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-program.delete');
    }
}
