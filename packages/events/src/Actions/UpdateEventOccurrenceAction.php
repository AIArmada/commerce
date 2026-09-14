<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Events\EventOccurrenceUpdated;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\States\OccurrenceStatus\OccurrenceStatus as OccurrenceStatusState;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class UpdateEventOccurrenceAction
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    /**
     * Update mutable occurrence content and schedule fields while protecting
     * the event relationship and lifecycle timestamps.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{changes: array<string, array{old: mixed, new: mixed}>, occurrence: EventOccurrence}
     */
    public function handle(EventOccurrence $occurrence, array $attributes): array
    {
        EventWriteGuard::findOrFail($occurrence->event_id);

        $original = $occurrence->getRawOriginal();

        $fillable = $occurrence->getFillable();
        $allowed = array_intersect_key($attributes, array_flip($fillable));
        unset(
            $allowed['event_id'],
            $allowed['published_at'],
            $allowed['delayed_at'],
            $allowed['postponed_at'],
            $allowed['rescheduled_at'],
            $allowed['cancelled_at'],
            $allowed['completed_at'],
            $allowed['archived_at'],
            $allowed['rescheduled_from_occurrence_id'],
            $allowed['rescheduled_to_occurrence_id'],
        );

        if (array_key_exists('title', $allowed) && blank($allowed['title'])) {
            throw new InvalidArgumentException('Occurrence title is required.');
        }

        if (array_key_exists('title', $allowed)) {
            $allowed['title'] = $this->contentNormalizer->normalizeTitle((string) $allowed['title']);
        }

        $startsAt = array_key_exists('starts_at', $allowed)
            ? CarbonImmutable::parse((string) $allowed['starts_at'])
            : $occurrence->starts_at;
        $endsAt = array_key_exists('ends_at', $allowed)
            ? CarbonImmutable::parse((string) $allowed['ends_at'])
            : $occurrence->ends_at;

        if ($endsAt instanceof CarbonImmutable && $startsAt instanceof CarbonImmutable && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Occurrence end time must be after the start time.');
        }

        $statusValue = $allowed['status'] ?? null;
        unset($allowed['status']);

        if ($statusValue !== null && ! is_string($statusValue)) {
            throw new InvalidArgumentException('Occurrence status must be a state name.');
        }

        $occurrence->update($allowed);

        if ($statusValue !== null) {
            $this->transitionStatus($occurrence, $statusValue);
        }

        $occurrence->refresh();

        $current = $occurrence->getAttributes();

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
            event(new EventOccurrenceUpdated($occurrence, $changes));
        }

        if (isset($changes['status'])) {
            $changeType = match ($occurrence->status->getValue()) {
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
                    eventId: $occurrence->event_id,
                    changeType: $changeType,
                    reason: $occurrence->status_reason,
                    occurrenceId: $occurrence->id,
                    oldValue: ['status' => $original['status'] ?? null],
                    newValue: ['status' => $occurrence->status->getValue()],
                );
            }
        }

        return [
            'changes' => $changes,
            'occurrence' => $occurrence->fresh(),
        ];
    }

    private function transitionStatus(EventOccurrence $occurrence, string $statusValue): void
    {
        if ($occurrence->status->getValue() === $statusValue) {
            return;
        }

        $stateClass = OccurrenceStatusState::resolveStateClass($statusValue);

        if (! is_string($stateClass) || ! is_a($stateClass, OccurrenceStatusState::class, true)) {
            throw new InvalidArgumentException(sprintf('Unknown occurrence status [%s].', $statusValue));
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
            $occurrence->{$timestampField} = CarbonImmutable::now();
        }

        $occurrence->status->transitionTo($stateClass);
    }
}
