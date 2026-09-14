<?php

declare(strict_types=1);

namespace AIArmada\Events\Policies;

use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Policies\Concerns\ResolvesEventManager;
use Illuminate\Auth\Access\HandlesAuthorization;

final class EventSessionPolicy
{
    use HandlesAuthorization;
    use ResolvesEventManager;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, EventSession $session): bool
    {
        $event = $session->event;

        return $event?->isPubliclyVisible() === true
            || $this->canManageEvent($user, 'view', $event);
    }

    public function create(mixed $user): bool
    {
        return $this->canCreateForOwner($user);
    }

    public function update(mixed $user, EventSession $session): bool
    {
        return $this->canManageEvent($user, 'update', $session->event);
    }

    public function delete(mixed $user, EventSession $session): bool
    {
        return $this->canManageEvent($user, 'delete', $session->event);
    }
}
