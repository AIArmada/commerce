<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Policies;

use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Platform\ResolvesEventManager;
use Illuminate\Auth\Access\HandlesAuthorization;

final class EventOccurrencePolicy
{
    use HandlesAuthorization;
    use ResolvesEventManager;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, EventOccurrence $occurrence): bool
    {
        $event = $occurrence->event;

        return ($event?->isPubliclyVisible() ?? false)
            || $this->canManageEvent($user, 'view', $event);
    }

    public function create(mixed $user): bool
    {
        return $this->canCreateForOwner($user);
    }

    public function update(mixed $user, EventOccurrence $occurrence): bool
    {
        return $this->canManageEvent($user, 'update', $occurrence->event);
    }

    public function delete(mixed $user, EventOccurrence $occurrence): bool
    {
        return $this->canManageEvent($user, 'delete', $occurrence->event);
    }
}
