<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Events\EventSessionUpdated;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventWriteGuard;
use Carbon\CarbonImmutable;

final class UpdateEventSessionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{changes: array<string, array{old: mixed, new: mixed}>, session: EventSession}
     */
    public function handle(EventSession $session, array $attributes): array
    {
        EventWriteGuard::findOrFail($session->event_id);

        $original = $session->getOriginal();

        $fillable = $session->getFillable();
        $allowed = array_intersect_key($attributes, array_flip($fillable));
        unset(
            $allowed['event_id'],
            $allowed['event_occurrence_id'],
            $allowed['published_at'],
            $allowed['delayed_at'],
            $allowed['postponed_at'],
            $allowed['rescheduled_at'],
            $allowed['cancelled_at'],
            $allowed['completed_at'],
            $allowed['archived_at'],
        );

        if (array_key_exists('status', $allowed)) {
            $timestampField = match ((string) $allowed['status']) {
                'published' => 'published_at',
                'delayed' => 'delayed_at',
                'postponed' => 'postponed_at',
                'rescheduled' => 'rescheduled_at',
                'cancelled' => 'cancelled_at',
                'completed' => 'completed_at',
                'archived' => 'archived_at',
                default => null,
            };

            if ($timestampField !== null && ! array_key_exists($timestampField, $allowed)) {
                $allowed[$timestampField] = CarbonImmutable::now();
            }
        }

        $session->update($allowed);

        $changes = [];
        foreach ($allowed as $key => $newValue) {
            $oldValue = $original[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        if ($changes !== []) {
            event(new EventSessionUpdated($session, $changes));
        }

        if (isset($changes['status'])) {
            $changeType = match ($session->status->getValue()) {
                'published' => 'published',
                'delayed' => 'delayed',
                'cancelled' => 'cancelled',
                'postponed' => 'postponed',
                'rescheduled' => 'rescheduled',
                'completed' => 'completed',
                'archived' => 'archived',
                default => null,
            };

            if ($changeType !== null) {
                DispatchEventChangeChainAction::run(
                    eventId: $session->event_id,
                    changeType: $changeType,
                    reason: $session->status_reason,
                    sessionId: $session->id,
                    occurrenceId: $session->event_occurrence_id,
                    oldValue: ['status' => $original['status'] ?? null],
                    newValue: ['status' => $session->status->getValue()],
                );
            }
        }

        return [
            'changes' => $changes,
            'session' => $session->fresh(),
        ];
    }
}
