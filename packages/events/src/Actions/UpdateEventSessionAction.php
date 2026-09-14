<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Events\EventSessionUpdated;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\States\OccurrenceStatus\OccurrenceStatus as OccurrenceStatusState;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class UpdateEventSessionAction
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{changes: array<string, array{old: mixed, new: mixed}>, session: EventSession}
     */
    public function handle(EventSession $session, array $attributes): array
    {
        EventWriteGuard::findOrFail($session->event_id);

        $original = $session->getRawOriginal();

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

        if (array_key_exists('title', $allowed) && blank($allowed['title'])) {
            throw new InvalidArgumentException('Session title is required.');
        }

        if (array_key_exists('title', $allowed)) {
            $allowed['title'] = $this->contentNormalizer->normalizeTitle((string) $allowed['title']);
        }

        if (array_key_exists('summary', $allowed)) {
            $allowed['summary'] = $this->contentNormalizer->normalizeSummary($allowed['summary'] !== null ? (string) $allowed['summary'] : null);
        }

        if (array_key_exists('description', $allowed)) {
            $allowed['description'] = $this->contentNormalizer->normalizeDescription($allowed['description'] !== null ? (string) $allowed['description'] : null);
        }

        $statusValue = $allowed['status'] ?? null;
        unset($allowed['status']);

        if ($statusValue !== null && ! is_string($statusValue)) {
            throw new InvalidArgumentException('Session status must be a state name.');
        }

        $session->update($allowed);

        if ($statusValue !== null) {
            $this->transitionStatus($session, $statusValue);
        }

        $session->refresh();

        $current = $session->getAttributes();

        $changes = [];
        foreach ($allowed as $key => $newValue) {
            $oldValue = $original[$key] ?? null;
            $normalizedNewValue = $current[$key] ?? null;

            if ($oldValue !== $normalizedNewValue) {
                $changes[$key] = ['old' => $oldValue, 'new' => $normalizedNewValue];
            }
        }

        if ($statusValue !== null && ($original['status'] ?? null) !== ($current['status'] ?? null)) {
            $changes['status'] = ['old' => $original['status'] ?? null, 'new' => $current['status'] ?? null];
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

    private function transitionStatus(EventSession $session, string $statusValue): void
    {
        if ($session->status->getValue() === $statusValue) {
            return;
        }

        $stateClass = OccurrenceStatusState::resolveStateClass($statusValue);

        if (! is_string($stateClass) || ! is_a($stateClass, OccurrenceStatusState::class, true)) {
            throw new InvalidArgumentException(sprintf('Unknown session status [%s].', $statusValue));
        }

        $timestampField = match ($statusValue) {
            'published' => 'published_at',
            'delayed' => 'delayed_at',
            'postponed' => 'postponed_at',
            'rescheduled' => 'rescheduled_at',
            'cancelled' => 'cancelled_at',
            'completed' => 'completed_at',
            'archived' => 'archived_at',
            default => null,
        };

        if ($timestampField !== null) {
            $session->{$timestampField} = CarbonImmutable::now();
        }

        $session->status->transitionTo($stateClass);
    }
}
