<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class InventoryAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('inventory-allocation.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('inventory-allocation.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('inventory-allocation.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('inventory-allocation.delete');
    }
}
