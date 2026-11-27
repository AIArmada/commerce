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
        if ($this->isRegistrant($user, $registration)) {
            return true;
        }

        $event = $registration->event;

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

    public function requestRefund(mixed $user, EventRegistration $registration): bool
    {
        if ($this->isRegistrant($user, $registration)) {
            return true;
        }

        $event = $registration->event;

        return $this->canManageEvent($user, 'requestRefund', $event instanceof Model ? $event : null);
    }

    private function isRegistrant(mixed $user, EventRegistration $registration): bool
    {
        if (! $user instanceof Model) {
            return false;
        }

        $registrantType = $registration->getAttribute('registrant_type');
        $registrantId = $registration->getAttribute('registrant_id');

        return $registrantType !== null
            && $registrantId !== null
            && $registrantType === $user->getMorphClass()
            && (string) $registrantId === (string) $user->getKey();
    }
}
