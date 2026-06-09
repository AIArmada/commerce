<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class InventoryLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('inventory-level.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('inventory-level.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('inventory-level.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('inventory-level.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('inventory-level.delete');
    }
}
