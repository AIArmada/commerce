<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Policies;

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Platform\ResolvesEventManager;
use Illuminate\Auth\Access\HandlesAuthorization;

final class EventPolicy
{
    use HandlesAuthorization;
    use ResolvesEventManager;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, Event $event): bool
    {
        return $event->isPubliclyVisible() || $this->canManageEvent($user, 'view', $event);
    }

    public function create(mixed $user): bool
    {
        return $this->canCreateForOwner($user);
    }

    public function update(mixed $user, Event $event): bool
    {
        return $this->canManageEvent($user, 'update', $event);
    }

    public function delete(mixed $user, Event $event): bool
    {
        return false;
    }

    public function publish(mixed $user, Event $event): bool
    {
        return $this->canManageEvent($user, 'publish', $event);
    }

    public function archive(mixed $user, Event $event): bool
    {
        return $this->canManageEvent($user, 'archive', $event);
    }

    public function cancel(mixed $user, Event $event): bool
    {
        return $this->canManageEvent($user, 'cancel', $event);
    }
}
