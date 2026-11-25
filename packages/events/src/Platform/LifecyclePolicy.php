<?php

declare(strict_types=1);

namespace AIArmada\Events\Platform;

final class LifecyclePolicy
{
    public function canAcceptRegistrations(string $occurrenceStatus): bool
    {
        $statuses = config('events.lifecycle.occurrence.registration_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($occurrenceStatus, $statuses, true);
    }

    public function canAcceptRegistrationsForEvent(string $eventStatus): bool
    {
        $blocked = config(
            'events.lifecycle.event.registration_blocked_statuses',
            ['cancelled', 'completed', 'archived', 'expired', 'voided'],
        );

        return ! in_array($eventStatus, $blocked, true);
    }

    public function canAcceptRegistrationsForSession(string $sessionStatus): bool
    {
        $statuses = config('events.lifecycle.session.registration_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($sessionStatus, $statuses, true);
    }

    public function canCheckIn(string $occurrenceStatus): bool
    {
        $statuses = config('events.lifecycle.occurrence.check_in_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($occurrenceStatus, $statuses, true);
    }

    public function canWalkIn(string $occurrenceStatus): bool
    {
        $statuses = config('events.lifecycle.occurrence.walk_in_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($occurrenceStatus, $statuses, true);
    }

    public function canCheckInRegistration(string $registrationStatus): bool
    {
        $statuses = config('events.lifecycle.registration.check_in_allowed_statuses', ['confirmed']);

        return in_array($registrationStatus, $statuses, true);
    }

    public function isCapacityBlocking(string $registrationStatus): bool
    {
        $statuses = config('events.lifecycle.registration.capacity_blocking_statuses', ['pending', 'confirmed', 'checked_in']);

        return in_array($registrationStatus, $statuses, true);
    }

    public function isTerminal(string $registrationStatus): bool
    {
        $statuses = config('events.lifecycle.registration.terminal_statuses', ['checked_in', 'cancelled', 'refunded', 'no_show']);

        return in_array($registrationStatus, $statuses, true);
    }

    public function shouldAutoPromoteWaitlist(): bool
    {
        return (bool) config('events.lifecycle.registration.auto_promote_waitlist', false);
    }
}
