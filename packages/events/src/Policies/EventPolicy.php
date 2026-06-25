<?php

declare(strict_types=1);

namespace AIArmada\Events\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\CanManageEventsFor;
use AIArmada\Events\Models\Event;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

final class EventPolicy
{
    use HandlesAuthorization;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, Event $event): bool
    {
        return $event->isPubliclyVisible() || $this->canManage($user, 'view', $event);
    }

    public function create(mixed $user): bool
    {
        if (! Event::ownerScopeConfig()->enabled) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if (! $owner instanceof Model) {
            return false;
        }

        if ($owner instanceof CanManageEventsFor) {
            return $owner->canManageEventsFor($user, 'create');
        }

        return $user instanceof Model && $this->sameModel($user, $owner);
    }

    public function update(mixed $user, Event $event): bool
    {
        return $this->canManage($user, 'update', $event);
    }

    public function delete(mixed $user, Event $event): bool
    {
        return false;
    }

    public function publish(mixed $user, Event $event): bool
    {
        return $this->canManage($user, 'publish', $event);
    }

    public function archive(mixed $user, Event $event): bool
    {
        return $this->canManage($user, 'archive', $event);
    }

    public function cancel(mixed $user, Event $event): bool
    {
        return $this->canManage($user, 'cancel', $event);
    }

    private function canManage(mixed $user, string $ability, Event $event): bool
    {
        if (! Event::ownerScopeConfig()->enabled) {
            return true;
        }

        $owner = $event->owner;

        if (! $owner instanceof Model) {
            return false;
        }

        if ($owner instanceof CanManageEventsFor) {
            return $owner->canManageEventsFor($user, $ability, $event);
        }

        return $user instanceof Model && $this->sameModel($user, $owner);
    }

    private function sameModel(Model $left, Model $right): bool
    {
        return $left->getMorphClass() === $right->getMorphClass()
            && (string) $left->getKey() === (string) $right->getKey();
    }
}
