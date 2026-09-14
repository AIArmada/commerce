<?php

declare(strict_types=1);

namespace AIArmada\Events\Policies;

use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Policies\Concerns\ResolvesEventManager;
use Illuminate\Auth\Access\HandlesAuthorization;

final class EventRegistrationPolicy
{
    use HandlesAuthorization;
    use ResolvesEventManager;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, EventRegistration $registration): bool
    {
        $event = $registration->event;

        return $event?->isPubliclyVisible() === true
            || $this->canManageEvent($user, 'view', $event);
    }

    public function create(mixed $user): bool
    {
        return $this->canCreateForOwner($user);
    }

    public function update(mixed $user, EventRegistration $registration): bool
    {
        return $this->canManageEvent($user, 'update', $registration->event);
    }

    public function delete(mixed $user, EventRegistration $registration): bool
    {
        return $this->canManageEvent($user, 'delete', $registration->event);
    }
}
