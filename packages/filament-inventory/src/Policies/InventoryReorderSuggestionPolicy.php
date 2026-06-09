<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class InventoryReorderSuggestionPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('inventory-reorder-suggestion.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('inventory-reorder-suggestion.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('inventory-reorder-suggestion.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('inventory-reorder-suggestion.delete');
    }
}
