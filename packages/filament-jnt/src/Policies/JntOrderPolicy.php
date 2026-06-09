<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Jnt\Models\JntOrder;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class JntOrderPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return OwnerUiScope::canCreate(JntOrder::class);
    }

    public function view(Authorizable $user, JntOrder $order): bool
    {
        return OwnerUiScope::canAccessRecord($order);
    }

    public function create(Authorizable $user): bool
    {
        return OwnerUiScope::canCreate(JntOrder::class);
    }

    public function update(Authorizable $user, JntOrder $order): bool
    {
        return OwnerUiScope::canMutateRecord($order);
    }

    public function delete(Authorizable $user, JntOrder $order): bool
    {
        return OwnerUiScope::canMutateRecord($order);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return OwnerUiScope::canCreate(JntOrder::class);
    }

    public function restore(Authorizable $user, JntOrder $order): bool
    {
        return OwnerUiScope::canMutateRecord($order);
    }

    public function forceDelete(Authorizable $user, JntOrder $order): bool
    {
        return OwnerUiScope::canMutateRecord($order);
    }
}
