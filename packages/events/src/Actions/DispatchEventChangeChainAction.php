<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\EventChangeLog;
use AIArmada\Events\Models\EventNotificationBatch;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventUpdate;
use AIArmada\Events\Support\EventWriteGuard;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class DispatchEventChangeChainAction
{
    use AsAction;

    public function handle(
        string $eventId,
        string $changeType,
        string $changeCategory = 'administration',
        string $impactLevel = 'low',
        bool $requiresNotification = false,
        ?string $reason = null,
        ?string $occurrenceId = null,
        ?string $sessionId = null,
        array $oldValue = [],
        array $newValue = [],
    ): void {
        $event = EventWriteGuard::findOrFail($eventId);

        if ($occurrenceId !== null) {
            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $event->getKey())
                ->first();

            if ($occurrence === null) {
                throw new InvalidArgumentException('The selected occurrence does not belong to the selected event.');
            }
        }

        if ($sessionId !== null) {
            $session = EventSession::query()
                ->whereKey($sessionId)
                ->where('event_id', $event->getKey())
                ->first();

            if ($session === null) {
                throw new InvalidArgumentException('The selected session does not belong to the selected event.');
            }

            if ($occurrenceId !== null && $session->event_occurrence_id !== $occurrenceId) {
                throw new InvalidArgumentException('The selected session does not belong to the selected occurrence.');
            }
        }

        OwnerContext::withOwner($event->owner, function () use (
            $changeCategory,
            $changeType,
            $event,
            $impactLevel,
            $newValue,
            $oldValue,
            $occurrenceId,
            $reason,
            $requiresNotification,
            $sessionId,
        ): void {
            $subjectType = $sessionId ? 'event_session' : ($occurrenceId ? 'event_occurrence' : 'event');

            $changeLog = EventChangeLog::query()->create([
                'event_id' => $event->getKey(),
                'event_occurrence_id' => $occurrenceId,
                'event_session_id' => $sessionId,
                'subject_type' => $subjectType,
                'subject_id' => $sessionId ?? $occurrenceId ?? $event->getKey(),
                'change_type' => $changeType,
                'change_category' => $changeCategory,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => $reason,
                'impact_level' => $impactLevel,
                'visibility' => $this->impactVisibility($impactLevel),
                'requires_notification' => $requiresNotification,
                'changed_at' => CarbonImmutable::now(),
            ]);

            $this->createEventUpdateIfNeeded($changeLog, $changeType, $impactLevel, $reason, $oldValue, $newValue);

            if ($requiresNotification) {
                $this->createNotificationBatch($changeLog, $impactLevel);
            }
        });
    }

    private function createEventUpdateIfNeeded(
        EventChangeLog $changeLog,
        string $changeType,
        string $impactLevel,
        ?string $reason,
        array $oldValue,
        array $newValue,
    ): ?EventUpdate {
        $updateTypes = [
            'cancelled' => 'cancellation',
            'postponed' => 'postponement',
            'rescheduled' => 'schedule_change',
            'published' => 'notice',
            'venue_changed' => 'venue_change',
            'speaker_changed' => 'speaker_change',
            'topic_changed' => 'topic_change',
            'delayed' => 'delay',
        ];

        $updateType = $updateTypes[$changeType] ?? null;
        if ($updateType === null) {
            return null;
        }

        $severity = match ($impactLevel) {
            'critical' => 'critical',
            'high' => 'urgent',
            'medium' => 'important',
            default => 'info',
        };

        $titles = [
            'cancellation' => 'Event Cancelled',
            'postponement' => 'Event Postponed',
            'schedule_change' => 'Schedule Changed',
            'venue_change' => 'Venue Changed',
            'speaker_change' => 'Speaker Lineup Changed',
            'topic_change' => 'Topic Updated',
            'delay' => 'Event Delayed',
            'notice' => 'Event Published',
        ];

        $title = $titles[$updateType];

        $messages = [
            'cancellation' => $reason ?? 'This event has been cancelled.',
            'postponement' => $reason ?? 'This event has been postponed. A new date will be announced.',
            'venue_change' => $this->buildBeforeAfter($oldValue, $newValue, 'venue'),
            'speaker_change' => $this->buildBeforeAfter($oldValue, $newValue, 'speaker'),
            'topic_change' => $this->buildBeforeAfter($oldValue, $newValue, 'topic'),
        ];

        $message = $messages[$updateType] ?? $reason ?? 'An update has been published.';

        $update = EventUpdate::query()->create([
            'event_id' => $changeLog->event_id,
            'event_occurrence_id' => $changeLog->event_occurrence_id,
            'event_session_id' => $changeLog->event_session_id,
            'event_change_log_id' => $changeLog->id,
            'update_type' => $updateType,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'visibility' => 'public',
            'is_pinned' => in_array($impactLevel, ['critical', 'high'], true),
            'published_at' => CarbonImmutable::now(),
        ]);

        if (! empty($oldValue) || ! empty($newValue)) {
            foreach ($oldValue as $key => $old) {
                $new = $newValue[$key] ?? null;
                if ($old !== $new) {
                    $update->items()->create([
                        'field_key' => $key,
                        'old_value' => is_string($old) ? $old : json_encode($old),
                        'new_value' => is_string($new) ? $new : json_encode($new),
                    ]);
                }
            }
        }

        return $update;
    }

    private function createNotificationBatch(EventChangeLog $changeLog, string $impactLevel): EventNotificationBatch
    {
        return EventNotificationBatch::query()->create([
            'event_id' => $changeLog->event_id,
            'event_occurrence_id' => $changeLog->event_occurrence_id,
            'event_session_id' => $changeLog->event_session_id,
            'event_change_log_id' => $changeLog->id,
            'audience_scope' => $impactLevel === 'critical' ? 'registrants' : 'followers',
            'title' => 'Notification: ' . ucfirst(str_replace('_', ' ', $changeLog->change_type)),
            'status' => 'pending',
        ]);
    }

    private function impactVisibility(string $impactLevel): string
    {
        return match ($impactLevel) {
            'critical', 'high' => 'public',
            'medium' => 'registered_only',
            default => 'managers_only',
        };
    }

    private function buildBeforeAfter(array $oldValue, array $newValue, string $key): string
    {
        $old = $oldValue[$key] ?? $oldValue['old'] ?? null;
        $new = $newValue[$key] ?? $newValue['new'] ?? null;

        if ($old !== null && $new !== null) {
            return "Changed from {$old} to {$new}.";
        }
        if ($new) {
            return "Updated to {$new}.";
        }

        return 'A change has been made.';
    }
}
