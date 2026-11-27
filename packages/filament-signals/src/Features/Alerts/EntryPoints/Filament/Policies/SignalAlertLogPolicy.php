<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class SignalAlertLogPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('signal-alert-log.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-alert-log.view');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-alert-log.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-alert-log.delete');
    }
}
