<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class SignalAlertRulePolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('signal-alert-rule.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-alert-rule.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('signal-alert-rule.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-alert-rule.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-alert-rule.delete');
    }
}
