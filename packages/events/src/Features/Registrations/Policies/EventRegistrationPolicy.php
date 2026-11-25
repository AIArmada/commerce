<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Policies;

use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Platform\ResolvesEventManager;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

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

        if ($event instanceof Model
            && method_exists($event, 'isPubliclyVisible')
            && $event->isPubliclyVisible() === true) {
            return true;
        }

        return $this->canManageEvent($user, 'view', $event instanceof Model ? $event : null);
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
