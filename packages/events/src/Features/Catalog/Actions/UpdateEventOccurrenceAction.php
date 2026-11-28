<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Events\EventOccurrenceUpdated;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\OccurrenceStatus as OccurrenceStatusState;
use AIArmada\Events\Features\Changes\Api\ChangesApi;
use AIArmada\Events\Platform\EventContentNormalizer;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
        [$occurrence, $changes, $original] = DB::transaction(function () use ($occurrence, $attributes): array {
            /** @var EventOccurrence $occurrence */
            $occurrence = $occurrence->newQuery()
                ->withoutOwnerScope()
                ->whereKey($occurrence->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $event = EventWriteGuard::findOrFail((string) $occurrence->getAttribute('event_id'));

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

            if (array_key_exists('starts_at', $allowed) && blank($allowed['starts_at'])) {
                throw new InvalidArgumentException('Occurrence start time is required.');
            }

            $startsAt = array_key_exists('starts_at', $allowed)
                ? CarbonImmutable::parse($allowed['starts_at'])
                : $occurrence->starts_at;
            $endsAt = ! array_key_exists('ends_at', $allowed)
                ? $occurrence->ends_at
                : ($allowed['ends_at'] === null ? null : CarbonImmutable::parse($allowed['ends_at']));

            if ($endsAt instanceof CarbonImmutable && $startsAt instanceof CarbonImmutable && $endsAt->lessThanOrEqualTo($startsAt)) {
                throw new InvalidArgumentException('Occurrence end time must be after the start time.');
            }

            if (array_key_exists('visibility', $allowed) && blank($allowed['visibility'])) {
                $allowed['visibility'] = $event->getAttribute('visibility') ?: 'public';
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

            return [$occurrence, $changes, $original];
        });

        if ($changes !== []) {
            DB::afterCommit(static fn () => event(new EventOccurrenceUpdated(
                (string) $occurrence->getKey(),
                (string) $occurrence->event_id,
                $changes,
            )));
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
                app(ChangesApi::class)->dispatchChange(
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
