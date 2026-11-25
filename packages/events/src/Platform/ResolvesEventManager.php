<?php

declare(strict_types=1);

namespace AIArmada\Events\Platform;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\Events\Contracts\CanManageEventsFor;
use Illuminate\Database\Eloquent\Model;

trait ResolvesEventManager
{
    private function canManageEvent(mixed $user, string $ability, ?Model $event): bool
    {
        if (! $event instanceof Model) {
            return false;
        }

        if (! $this->eventOwnerScopeEnabled()) {
            return true;
        }

        $owner = $event->getRelationValue('owner');

        if (! $owner instanceof Model) {
            return false;
        }

        if ($owner instanceof CanManageEventsFor) {
            return $owner->canManageEventsFor($user, $ability, $event);
        }

        return $user instanceof Model && $this->sameModel($user, $owner);
    }

    private function canManageOwnerTarget(mixed $user, string $ability, ?Model $target): bool
    {
        if (! $target instanceof Model) {
            return false;
        }

        if (! $this->eventOwnerScopeEnabled()) {
            return true;
        }

        if ($target instanceof CanManageEventsFor) {
            return $target->canManageEventsFor($user, $ability);
        }

        return $user instanceof Model && $this->sameModel($user, $target);
    }

    private function canCreateForOwner(mixed $user): bool
    {
        if (! $this->eventOwnerScopeEnabled()) {
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

    private function eventOwnerScopeEnabled(): bool
    {
        return OwnerScopeConfig::fromConfig('events.features.owner', true)->enabled;
    }

    private function sameModel(Model $left, Model $right): bool
    {
        return $left->getMorphClass() === $right->getMorphClass()
            && (string) $left->getKey() === (string) $right->getKey();
    }
}
