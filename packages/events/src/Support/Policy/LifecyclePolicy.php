<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Policy;

use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;

final class LifecyclePolicy
{
    public function canAcceptRegistrations(EventOccurrence $occurrence): bool
    {
        $statuses = config('events.lifecycle.occurrence.registration_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($occurrence->status, $statuses, true);
    }

    public function canCheckIn(EventOccurrence $occurrence): bool
    {
        $statuses = config('events.lifecycle.occurrence.check_in_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($occurrence->status, $statuses, true);
    }

    public function canWalkIn(EventOccurrence $occurrence): bool
    {
        $statuses = config('events.lifecycle.occurrence.walk_in_accepting_statuses', ['scheduled', 'published', 'live']);

        return in_array($occurrence->status, $statuses, true);
    }

    public function canCheckInRegistration(EventRegistration $registration): bool
    {
        $statuses = config('events.lifecycle.registration.check_in_allowed_statuses', ['confirmed']);

        return in_array($registration->status, $statuses, true);
    }

    public function isCapacityBlocking(EventRegistration $registration): bool
    {
        $statuses = config('events.lifecycle.registration.capacity_blocking_statuses', ['pending', 'confirmed', 'checked_in', 'no_show']);

        return in_array($registration->status, $statuses, true);
    }

    public function isTerminal(EventRegistration $registration): bool
    {
        $statuses = config('events.lifecycle.registration.terminal_statuses', ['checked_in', 'cancelled', 'refunded', 'no_show']);

        return in_array($registration->status, $statuses, true);
    }

    public function shouldAutoPromoteWaitlist(): bool
    {
        return (bool) config('events.lifecycle.registration.auto_promote_waitlist', false);
    }
}
