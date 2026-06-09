<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class SignalGoalPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('signal-goal.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-goal.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('signal-goal.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-goal.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-goal.delete');
    }
}
