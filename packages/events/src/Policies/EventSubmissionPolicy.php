<?php

declare(strict_types=1);

namespace AIArmada\Events\Policies;

use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\Policies\Concerns\ResolvesEventManager;
use Illuminate\Auth\Access\HandlesAuthorization;

final class EventSubmissionPolicy
{
    use HandlesAuthorization;
    use ResolvesEventManager;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, EventSubmission $submission): bool
    {
        $event = $submission->event;

        if ($event !== null) {
            return $event->isPubliclyVisible()
                || $this->canManageEvent($user, 'view', $event);
        }

        return $this->canManageOwnerTarget($user, 'view', $submission->target);
    }

    public function create(mixed $user): bool
    {
        return $this->canCreateForOwner($user);
    }

    public function update(mixed $user, EventSubmission $submission): bool
    {
        $event = $submission->event;

        if ($event !== null) {
            return $this->canManageEvent($user, 'update', $event);
        }

        return $this->canManageOwnerTarget($user, 'update', $submission->target);
    }

    public function delete(mixed $user, EventSubmission $submission): bool
    {
        $event = $submission->event;

        if ($event !== null) {
            return $this->canManageEvent($user, 'delete', $event);
        }

        return $this->canManageOwnerTarget($user, 'delete', $submission->target);
    }
}
