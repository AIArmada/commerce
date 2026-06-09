<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class SignalSegmentPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('signal-segment.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-segment.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('signal-segment.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-segment.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('signal-segment.delete');
    }
}
