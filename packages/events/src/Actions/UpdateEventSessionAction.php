<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Events\EventSessionUpdated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSession;

final class UpdateEventSessionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{changes: array<string, array{old: mixed, new: mixed}>, session: EventSession}
     */
    public function handle(EventSession $session, array $attributes): array
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $session->event_id);

        $original = $session->getOriginal();

        $fillable = $session->getFillable();
        $allowed = array_intersect_key($attributes, array_flip($fillable));
        unset(
            $allowed['event_id'],
            $allowed['event_occurrence_id'],
            $allowed['published_at'],
            $allowed['delayed_at'],
            $allowed['postponed_at'],
            $allowed['cancelled_at'],
            $allowed['completed_at'],
            $allowed['archived_at'],
        );

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
            $changeType = match ($session->status) {
                'published' => 'published',
                'cancelled' => 'cancelled',
                'postponed' => 'postponed',
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
                    newValue: ['status' => $session->status],
                );
            }
        }

        return [
            'changes' => $changes,
            'session' => $session->fresh(),
        ];
    }
}
