<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class TrackedPropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('tracked-property.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('tracked-property.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('tracked-property.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('tracked-property.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('tracked-property.delete');
    }
}
