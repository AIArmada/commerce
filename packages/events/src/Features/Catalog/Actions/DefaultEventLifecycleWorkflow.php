<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Events\EventArchived;
use AIArmada\Events\Events\EventCancelled;
use AIArmada\Events\Events\EventOccurrenceCancelled;
use AIArmada\Events\Events\EventOccurrenceCompleted;
use AIArmada\Events\Events\EventOccurrenceDelayed;
use AIArmada\Events\Events\EventOccurrencePostponed;
use AIArmada\Events\Events\EventOccurrenceRescheduled;
use AIArmada\Events\Events\EventPostponed;
use AIArmada\Events\Events\EventPublished;
use AIArmada\Events\Events\EventSessionCancelled;
use AIArmada\Events\Events\EventSessionCompleted;
use AIArmada\Events\Events\EventSessionDelayed;
use AIArmada\Events\Events\EventSessionPostponed;
use AIArmada\Events\Events\EventSessionRescheduled;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\States\EventStatus\Archived as EventArchivedState;
use AIArmada\Events\Features\Catalog\States\EventStatus\Cancelled as EventCancelledState;
use AIArmada\Events\Features\Catalog\States\EventStatus\Completed as EventCompletedState;
use AIArmada\Events\Features\Catalog\States\EventStatus\Postponed as EventPostponedState;
use AIArmada\Events\Features\Catalog\States\EventStatus\Published as EventPublishedState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Archived as OccurrenceArchivedState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Cancelled as OccurrenceCancelledState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Completed as OccurrenceCompletedState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Delayed as OccurrenceDelayedState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Postponed as OccurrencePostponedState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Rescheduled as OccurrenceRescheduledState;
use AIArmada\Events\Features\Changes\Api\ChangesApi;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DefaultEventLifecycleWorkflow implements EventLifecycleWorkflow
{
    public function publish(string $eventId): void
    {
        DB::transaction(function () use ($eventId): void {
            $event = $this->lockEvent($eventId);

            $event->published_at = CarbonImmutable::now();
            $event->status->transitionTo(EventPublishedState::class);
            $event->save();

            $this->recordChange($eventId, 'published');

            event(new EventPublished($eventId));
        });
    }

    public function cancel(string $targetType, string $targetId, ?string $reason = null): void
    {
        DB::transaction(function () use ($targetType, $targetId, $reason): void {
            $target = $this->lockTarget($targetType, $targetId);
            $context = $this->contextOf($target);

            $target->cancelled_at = CarbonImmutable::now();
            $target->status_reason = $reason;
            $target->status->transitionTo($targetType === 'event' ? EventCancelledState::class : OccurrenceCancelledState::class);
            $target->save();

            $this->recordChange($context['event_id'], 'cancelled', $reason, $context['occurrence_id'], $context['session_id']);

            match ($targetType) {
                'event' => event(new EventCancelled($context['event_id'], $context['occurrence_id'], $reason)),
                'occurrence' => event(new EventOccurrenceCancelled($targetId, $context['event_id'], $reason)),
                'session' => event(new EventSessionCancelled($targetId, $context['event_id'], $context['occurrence_id'], $reason)),
                default => null,
            };
        });
    }

    public function postpone(string $targetType, string $targetId, ?string $reason = null): void
    {
        DB::transaction(function () use ($targetType, $targetId, $reason): void {
            $target = $this->lockTarget($targetType, $targetId);
            $context = $this->contextOf($target);

            $target->postponed_at = CarbonImmutable::now();
            $target->status_reason = $reason;
            $target->status->transitionTo($targetType === 'event' ? EventPostponedState::class : OccurrencePostponedState::class);
            $target->save();

            $this->recordChange($context['event_id'], 'postponed', $reason, $context['occurrence_id'], $context['session_id']);

            match ($targetType) {
                'event' => event(new EventPostponed($context['event_id'], $context['occurrence_id'], $reason)),
                'occurrence' => event(new EventOccurrencePostponed($targetId, $context['event_id'], $reason)),
                'session' => event(new EventSessionPostponed($targetId, $context['event_id'], $context['occurrence_id'], $reason)),
                default => null,
            };
        });
    }

    public function delay(
        string $targetType,
        string $targetId,
        ?string $reason = null,
        ?DateTimeInterface $expectedStartsAt = null,
    ): void {
        if ($targetType === 'event') {
            throw new InvalidArgumentException('Events cannot be delayed; delay an occurrence or session instead.');
        }

        DB::transaction(function () use ($targetType, $targetId, $reason, $expectedStartsAt): void {
            $target = $this->lockTarget($targetType, $targetId);
            $context = $this->contextOf($target);

            $target->delayed_at = CarbonImmutable::now();
            $target->status_reason = $reason;
            $target->status->transitionTo(OccurrenceDelayedState::class);
            $target->save();

            $this->recordChange($context['event_id'], 'delayed', $reason, $context['occurrence_id'], $context['session_id']);

            $expected = $expectedStartsAt === null
                ? null
                : CarbonImmutable::createFromInterface($expectedStartsAt)->setTimezone('UTC');

            if ($targetType === 'session') {
                event(new EventSessionDelayed($targetId, $context['event_id'], $reason, $expected));
            } else {
                event(new EventOccurrenceDelayed($targetId, $context['event_id'], $reason, $expected));
            }
        });
    }

    public function reschedule(
        string $targetType,
        string $targetId,
        DateTimeInterface $newStartsAt,
        ?DateTimeInterface $newEndsAt = null,
        array $options = [],
    ): string {
        if ($targetType === 'event') {
            throw new InvalidArgumentException('Events cannot be rescheduled; reschedule an occurrence or session instead.');
        }

        return DB::transaction(function () use ($targetType, $targetId, $newStartsAt, $newEndsAt, $options): string {
            $target = $this->lockTarget($targetType, $targetId);
            $context = $this->contextOf($target);

            $startsAt = CarbonImmutable::createFromInterface($newStartsAt)->setTimezone('UTC');
            $endsAt = $newEndsAt === null
                ? null
                : CarbonImmutable::createFromInterface($newEndsAt)->setTimezone('UTC');

            if ($endsAt instanceof CarbonImmutable && $endsAt->lessThanOrEqualTo($startsAt)) {
                throw new InvalidArgumentException('Rescheduled end time must be after the start time.');
            }

            $previousStartsAt = $this->toImmutable($target->getAttribute('starts_at'));
            $previousEndsAt = $this->toImmutable($target->getAttribute('ends_at'));

            $target->starts_at = $startsAt;
            $target->ends_at = $endsAt;

            if (isset($options['timezone']) && is_string($options['timezone']) && $options['timezone'] !== '') {
                $target->timezone = $options['timezone'];
            }

            $target->rescheduled_at = CarbonImmutable::now();

            $currentStatus = (string) $target->status->getValue();

            if ($currentStatus === 'live') {
                throw new InvalidArgumentException('Live occurrences and sessions cannot be rescheduled.');
            }

            if ($currentStatus !== 'rescheduled') {
                $target->status->transitionTo(OccurrenceRescheduledState::class);
            }

            $target->save();

            $this->recordChange($context['event_id'], 'rescheduled', null, $context['occurrence_id'], $context['session_id'], [
                'starts_at' => $previousStartsAt,
                'ends_at' => $previousEndsAt,
            ], [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $occurrenceOrSessionEndsAt = $endsAt ?? $startsAt;

            if ($targetType === 'session') {
                event(new EventSessionRescheduled(
                    $targetId,
                    $context['event_id'],
                    $startsAt,
                    $occurrenceOrSessionEndsAt,
                    $context['occurrence_id'],
                    $previousStartsAt,
                    $previousEndsAt,
                ));
            } else {
                event(new EventOccurrenceRescheduled(
                    $targetId,
                    $context['event_id'],
                    $startsAt,
                    $occurrenceOrSessionEndsAt,
                    $previousStartsAt,
                    $previousEndsAt,
                ));
            }

            return $targetId;
        });
    }

    public function complete(string $targetType, string $targetId): void
    {
        DB::transaction(function () use ($targetType, $targetId): void {
            $target = $this->lockTarget($targetType, $targetId);
            $context = $this->contextOf($target);

            $target->completed_at = CarbonImmutable::now();
            $target->status->transitionTo($targetType === 'event' ? EventCompletedState::class : OccurrenceCompletedState::class);
            $target->save();

            $this->recordChange($context['event_id'], 'completed', null, $context['occurrence_id'], $context['session_id']);

            if ($targetType === 'session') {
                event(new EventSessionCompleted($targetId, $context['event_id']));
            } elseif ($targetType === 'occurrence') {
                event(new EventOccurrenceCompleted($targetId, $context['event_id']));
            }
        });
    }

    public function archive(string $targetType, string $targetId, ?string $reason = null): void
    {
        if ($targetType === 'session') {
            throw new InvalidArgumentException('Sessions cannot be archived; archive the event or occurrence instead.');
        }

        DB::transaction(function () use ($targetType, $targetId, $reason): void {
            $target = $this->lockTarget($targetType, $targetId);
            $context = $this->contextOf($target);

            $target->archived_at = CarbonImmutable::now();
            $target->status_reason = $reason;
            $target->status->transitionTo($targetType === 'event' ? EventArchivedState::class : OccurrenceArchivedState::class);
            $target->save();

            $this->recordChange($context['event_id'], 'archived', $reason, $context['occurrence_id'], $context['session_id']);

            if ($targetType === 'event') {
                event(new EventArchived($context['event_id'], $context['occurrence_id'], $reason));
            }
        });
    }

    private function lockEvent(string $eventId): Event
    {
        $eventClass = ModelResolver::eventClass();
        $event = $eventClass::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();

        EventWriteGuard::findOrFail($eventId);

        if (! $event instanceof Event) {
            throw new InvalidArgumentException('The configured events model must extend the Catalog Event model.');
        }

        return $event;
    }

    private function lockTarget(string $targetType, string $targetId): Event | EventOccurrence | EventSession
    {
        $class = match ($targetType) {
            'event' => ModelResolver::eventClass(),
            'occurrence' => ModelResolver::occurrenceClass(),
            'session' => ModelResolver::sessionClass(),
            default => throw new InvalidArgumentException(sprintf('Unknown lifecycle target type [%s].', $targetType)),
        };

        $target = $class::query()->whereKey($targetId)->lockForUpdate()->firstOrFail();

        if (! $target instanceof Event && ! $target instanceof EventOccurrence && ! $target instanceof EventSession) {
            throw new InvalidArgumentException('The configured events models must extend the Catalog models.');
        }

        $eventId = $target instanceof Event ? (string) $target->getKey() : (string) $target->getAttribute('event_id');

        EventWriteGuard::findOrFail($eventId);

        return $target;
    }

    private function toImmutable(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        return null;
    }

    /**
     * @return array{event_id: string, occurrence_id: ?string, session_id: ?string}
     */
    private function contextOf(Event | EventOccurrence | EventSession $target): array
    {
        if ($target instanceof Event) {
            return ['event_id' => (string) $target->getKey(), 'occurrence_id' => null, 'session_id' => null];
        }

        if ($target instanceof EventOccurrence) {
            return [
                'event_id' => (string) $target->event_id,
                'occurrence_id' => (string) $target->getKey(),
                'session_id' => null,
            ];
        }

        $occurrenceId = $target->event_occurrence_id;

        return [
            'event_id' => (string) $target->event_id,
            'occurrence_id' => $occurrenceId !== null ? (string) $occurrenceId : null,
            'session_id' => (string) $target->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValue
     * @param  array<string, mixed>  $newValue
     */
    private function recordChange(
        string $eventId,
        string $changeType,
        ?string $reason = null,
        ?string $occurrenceId = null,
        ?string $sessionId = null,
        array $oldValue = [],
        array $newValue = [],
    ): void {
        app(ChangesApi::class)->dispatchChange(
            eventId: $eventId,
            changeType: $changeType,
            changeCategory: $this->changeCategory($changeType),
            impactLevel: $this->impactLevel($changeType),
            requiresNotification: $this->requiresNotification($changeType),
            reason: $reason,
            occurrenceId: $occurrenceId,
            sessionId: $sessionId,
            oldValue: $oldValue,
            newValue: $newValue,
        );
    }

    private function changeCategory(string $changeType): string
    {
        return match ($changeType) {
            'cancelled', 'postponed', 'rescheduled', 'delayed' => 'status',
            default => 'administration',
        };
    }

    private function impactLevel(string $changeType): string
    {
        return match ($changeType) {
            'cancelled', 'postponed' => 'critical',
            'rescheduled', 'delayed' => 'high',
            'archived' => 'medium',
            default => 'low',
        };
    }

    private function requiresNotification(string $changeType): bool
    {
        return in_array($changeType, ['cancelled', 'postponed', 'rescheduled'], true);
    }
}
