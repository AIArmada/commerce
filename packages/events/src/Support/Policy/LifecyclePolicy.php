<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Policy;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;

final class LifecyclePolicy
{
    public function canAcceptRegistrations(EventOccurrence $occurrence): bool
    {
        $statuses = config('events.lifecycle.occurrence.registration_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($occurrence->status->getValue(), $statuses, true);
    }

    /**
     * Events block registrations only once they are closed. Draft and
     * scheduled events keep accepting registrations so staff flows can
     * register ahead of publication; the occurrence/session allowlists
     * below still gate the concrete scope.
     */
    public function canAcceptRegistrationsForEvent(Event $event): bool
    {
        $blocked = config(
            'events.lifecycle.event.registration_blocked_statuses',
            ['cancelled', 'completed', 'archived', 'expired', 'voided'],
        );

        return ! in_array($event->status->getValue(), $blocked, true);
    }

    public function canAcceptRegistrationsForSession(EventSession $session): bool
    {
        $statuses = config('events.lifecycle.session.registration_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($session->status->getValue(), $statuses, true);
    }

    public function canCheckIn(EventOccurrence $occurrence): bool
    {
        $statuses = config('events.lifecycle.occurrence.check_in_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($occurrence->status->getValue(), $statuses, true);
    }

    public function canWalkIn(EventOccurrence $occurrence): bool
    {
        $statuses = config('events.lifecycle.occurrence.walk_in_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($occurrence->status->getValue(), $statuses, true);
    }

    public function canCheckInRegistration(EventRegistration $registration): bool
    {
        $statuses = config('events.lifecycle.registration.check_in_allowed_statuses', ['confirmed']);

        return in_array($registration->status->getValue(), $statuses, true);
    }

    public function isCapacityBlocking(EventRegistration $registration): bool
    {
        $statuses = config('events.lifecycle.registration.capacity_blocking_statuses', ['pending', 'confirmed', 'checked_in']);

        return in_array($registration->status->getValue(), $statuses, true);
    }

    public function isTerminal(EventRegistration $registration): bool
    {
        $statuses = config('events.lifecycle.registration.terminal_statuses', ['checked_in', 'cancelled', 'refunded', 'no_show']);

        return in_array($registration->status->getValue(), $statuses, true);
    }

    public function shouldAutoPromoteWaitlist(): bool
    {
        return (bool) config('events.lifecycle.registration.auto_promote_waitlist', false);
    }
}
