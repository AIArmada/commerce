<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Jnt\Models\JntTrackingEvent;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class JntTrackingEventPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return OwnerUiScope::canCreate(JntTrackingEvent::class);
    }

    public function view(Authorizable $user, JntTrackingEvent $event): bool
    {
        return OwnerUiScope::canAccessRecord($event);
    }

    public function create(Authorizable $user): bool
    {
        return OwnerUiScope::canCreate(JntTrackingEvent::class);
    }

    public function update(Authorizable $user, JntTrackingEvent $event): bool
    {
        return OwnerUiScope::canMutateRecord($event);
    }

    public function delete(Authorizable $user, JntTrackingEvent $event): bool
    {
        return OwnerUiScope::canMutateRecord($event);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return OwnerUiScope::canCreate(JntTrackingEvent::class);
    }

    public function restore(Authorizable $user, JntTrackingEvent $event): bool
    {
        return OwnerUiScope::canMutateRecord($event);
    }

    public function forceDelete(Authorizable $user, JntTrackingEvent $event): bool
    {
        return OwnerUiScope::canMutateRecord($event);
    }
}
