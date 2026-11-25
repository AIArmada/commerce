<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Changes\Models\EventChangeLog;
use AIArmada\Events\Features\Changes\Models\EventUpdate;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DispatchEventChangeChainAction
{
    public function __construct(
        private readonly CatalogApi $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $oldValue
     * @param  array<string, mixed>  $newValue
     */
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

        OwnerContext::withOwner($event->getRelationValue('owner'), function () use (
            $changeCategory,
            $changeType,
            $eventId,
            $impactLevel,
            $newValue,
            $occurrenceId,
            $oldValue,
            $reason,
            $requiresNotification,
            $sessionId,
        ): void {
            if ($occurrenceId !== null) {
                $occurrenceRef = $this->catalog->resolveScope('occurrence', $occurrenceId);

                if ($occurrenceRef === null || $occurrenceRef->eventId !== $eventId) {
                    throw new InvalidArgumentException('The selected occurrence does not belong to the selected event.');
                }
            }

            if ($sessionId !== null) {
                $sessionRef = $this->catalog->resolveScope('session', $sessionId);

                if ($sessionRef === null || $sessionRef->eventId !== $eventId) {
                    throw new InvalidArgumentException('The selected session does not belong to the selected event.');
                }

                if ($occurrenceId !== null && $sessionRef->occurrenceId !== $occurrenceId) {
                    throw new InvalidArgumentException('The selected session does not belong to the selected occurrence.');
                }
            }

            DB::transaction(function () use (
                $changeCategory,
                $changeType,
                $eventId,
                $impactLevel,
                $newValue,
                $occurrenceId,
                $oldValue,
                $reason,
                $requiresNotification,
                $sessionId,
            ): void {
                $this->catalog->lockEvent($eventId);

                $subjectType = $sessionId !== null ? 'event_session' : ($occurrenceId !== null ? 'event_occurrence' : 'event');

                $changeLog = EventChangeLog::query()->create([
                    'event_id' => $eventId,
                    'event_occurrence_id' => $occurrenceId,
                    'event_session_id' => $sessionId,
                    'subject_type' => $subjectType,
                    'subject_id' => $sessionId ?? $occurrenceId ?? $eventId,
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

                $update = $this->createEventUpdateIfNeeded($changeLog, $changeType, $impactLevel, $reason, $oldValue, $newValue);

                if ($requiresNotification) {
                    event(new EventChangeNoticePublished(
                        (string) $changeLog->getKey(),
                        $eventId,
                        $update !== null ? (string) $update->getKey() : null,
                    ));
                }
            });
        });
    }

    /**
     * @param  array<string, mixed>  $oldValue
     * @param  array<string, mixed>  $newValue
     */
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

        $messages = [
            'cancellation' => $reason ?? 'This event has been cancelled.',
            'postponement' => $reason ?? 'This event has been postponed. A new date will be announced.',
            'venue_change' => $this->buildBeforeAfter($oldValue, $newValue, 'venue'),
            'speaker_change' => $this->buildBeforeAfter($oldValue, $newValue, 'speaker'),
            'topic_change' => $this->buildBeforeAfter($oldValue, $newValue, 'topic'),
        ];

        $update = EventUpdate::query()->create([
            'event_id' => $changeLog->event_id,
            'event_occurrence_id' => $changeLog->event_occurrence_id,
            'event_session_id' => $changeLog->event_session_id,
            'event_change_log_id' => $changeLog->getKey(),
            'update_type' => $updateType,
            'title' => $titles[$updateType],
            'message' => $messages[$updateType] ?? $reason ?? 'An update has been published.',
            'severity' => $severity,
            'visibility' => 'public',
            'is_pinned' => in_array($impactLevel, ['critical', 'high'], true),
            'published_at' => CarbonImmutable::now(),
        ]);

        if ($oldValue !== [] || $newValue !== []) {
            foreach ($oldValue as $key => $old) {
                $new = $newValue[$key] ?? null;

                if ($old === $new) {
                    continue;
                }

                $update->items()->create([
                    'field_key' => $key,
                    'old_value' => is_string($old) ? $old : json_encode($old),
                    'new_value' => is_string($new) ? $new : json_encode($new),
                ]);
            }
        }

        return $update;
    }

    private function impactVisibility(string $impactLevel): string
    {
        return match ($impactLevel) {
            'critical', 'high' => 'public',
            'medium' => 'registered_only',
            default => 'managers_only',
        };
    }

    /**
     * @param  array<string, mixed>  $oldValue
     * @param  array<string, mixed>  $newValue
     */
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
