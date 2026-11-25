<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Queries;

use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;

final class RegistrationCapacityQuery
{
    public function remaining(
        string $eventId,
        ?string $occurrenceId,
        ?string $sessionId,
        ?int $capacity,
    ): ?int {
        $remaining = $capacity === null
            ? null
            : max(0, $capacity - $this->reserved($eventId, $occurrenceId, $sessionId));

        if ($occurrenceId === null) {
            return $remaining;
        }

        $occurrenceClass = ModelResolver::occurrenceClass();

        /** @var Model|null $occurrence */
        $occurrence = $occurrenceClass::query()->whereKey($occurrenceId)->first();
        $occurrenceCapacity = $occurrence?->getAttribute('capacity');

        if ($occurrenceCapacity === null) {
            return $remaining;
        }

        $occurrenceRemaining = max(
            0,
            (int) $occurrenceCapacity - $this->reserved($eventId, $occurrenceId, null),
        );

        return $remaining === null ? $occurrenceRemaining : min($remaining, $occurrenceRemaining);
    }

    public function reserved(string $eventId, ?string $occurrenceId, ?string $sessionId): int
    {
        $query = EventRegistration::query()->where('event_id', $eventId);

        if ($occurrenceId !== null) {
            $query->where('event_occurrence_id', $occurrenceId);
        }

        if ($sessionId !== null) {
            $query->where('event_session_id', $sessionId);
        }

        return (int) $query
            ->whereIn('status', config(
                'events.lifecycle.registration.capacity_blocking_statuses',
                ['pending', 'confirmed', 'refund_pending', 'checked_in'],
            ))
            ->sum('total_participants');
    }
}
