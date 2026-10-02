<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Actions\DispatchEventChangeChainAction;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Events\EventArchived;
use AIArmada\Events\Events\EventCancelled;
use AIArmada\Events\Events\EventDelayed;
use AIArmada\Events\Events\EventOccurrenceCancelled;
use AIArmada\Events\Events\EventOccurrenceCompleted;
use AIArmada\Events\Events\EventOccurrencePostponed;
use AIArmada\Events\Events\EventOccurrenceRescheduled;
use AIArmada\Events\Events\EventPostponed;
use AIArmada\Events\Events\EventPublished;
use AIArmada\Events\Events\EventSessionCancelled;
use AIArmada\Events\Events\EventSessionCompleted;
use AIArmada\Events\Events\EventSessionDelayed;
use AIArmada\Events\Events\EventSessionPostponed;
use AIArmada\Events\Events\EventSessionRescheduled;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\States\EventStatus\Archived as EventArchivedState;
use AIArmada\Events\States\EventStatus\Cancelled as EventCancelledState;
use AIArmada\Events\States\EventStatus\Completed as EventCompletedState;
use AIArmada\Events\States\EventStatus\Postponed as EventPostponedState;
use AIArmada\Events\States\EventStatus\Published as EventPublishedState;
use AIArmada\Events\States\OccurrenceStatus\Archived as OccurrenceArchivedState;
use AIArmada\Events\States\OccurrenceStatus\Cancelled as OccurrenceCancelledState;
use AIArmada\Events\States\OccurrenceStatus\Completed as OccurrenceCompletedState;
use AIArmada\Events\States\OccurrenceStatus\Delayed as OccurrenceDelayedState;
use AIArmada\Events\States\OccurrenceStatus\Postponed as OccurrencePostponedState;
use AIArmada\Events\States\OccurrenceStatus\Rescheduled as OccurrenceRescheduledState;
use AIArmada\Events\Support\EventWriteGuard;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class DefaultEventLifecycleWorkflow implements EventLifecycleWorkflow
{
    public function publish(Event $event): void
    {
        $this->guardEvent($event);

        $event->published_at = CarbonImmutable::now();
        $event->status->transitionTo(EventPublishedState::class);

        $this->recordChange($event, 'published');

        event(new EventPublished($event));
    }

    public function cancel(Event | EventOccurrence | EventSession $target, ?string $reason = null): void
    {
        if ($target instanceof Event) {
            $this->guardEvent($target);
        } else {
            $this->guardChild($target);
        }

        $target->cancelled_at = CarbonImmutable::now();
        $target->status_reason = $reason;

        $target->status->transitionTo(
            $target instanceof Event
                ? EventCancelledState::class
                : OccurrenceCancelledState::class,
        );

        $this->recordChange($target, 'cancelled', $reason);

        if ($target instanceof Event) {
            event(new EventCancelled($target, $reason));
        } elseif ($target instanceof EventOccurrence) {
            event(new EventOccurrenceCancelled($target, $reason));
        } elseif ($target instanceof EventSession) {
            event(new EventSessionCancelled($target, $reason));
        }
    }

    public function postpone(Event | EventOccurrence | EventSession $target, ?string $reason = null): void
    {
        if ($target instanceof Event) {
            $this->guardEvent($target);
        } else {
            $this->guardChild($target);
        }

        $target->postponed_at = CarbonImmutable::now();
        $target->status_reason = $reason;

        $target->status->transitionTo(
            $target instanceof Event
                ? EventPostponedState::class
                : OccurrencePostponedState::class,
        );

        $this->recordChange($target, 'postponed', $reason);

        if ($target instanceof Event) {
            event(new EventPostponed($target, $reason));
        } elseif ($target instanceof EventOccurrence) {
            event(new EventOccurrencePostponed($target, $reason));
        } elseif ($target instanceof EventSession) {
            event(new EventSessionPostponed($target, $reason));
        }
    }

    public function delay(EventOccurrence | EventSession $target, ?string $reason = null, ?DateTimeInterface $expectedStartsAt = null): void
    {
        $this->guardChild($target);

        $target->delayed_at = CarbonImmutable::now();
        $target->status_reason = $reason;
        $target->status->transitionTo(OccurrenceDelayedState::class);

        $this->recordChange($target, 'delayed', $reason);

        if ($target instanceof EventOccurrence) {
            event(new EventDelayed($target, $reason, $expectedStartsAt));
        } elseif ($target instanceof EventSession) {
            event(new EventSessionDelayed($target, $reason, $expectedStartsAt));
        }
    }

    public function reschedule(EventOccurrence | EventSession $target, DateTimeInterface $newStartsAt, ?DateTimeInterface $newEndsAt = null, array $options = []): EventOccurrence | EventSession
    {
        $this->guardChild($target);

        $startsAt = CarbonImmutable::createFromInterface($newStartsAt)->setTimezone('UTC');
        $endsAt = $newEndsAt === null
            ? null
            : CarbonImmutable::createFromInterface($newEndsAt)->setTimezone('UTC');

        if ($endsAt instanceof CarbonImmutable && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Rescheduled end time must be after the start time.');
        }

        $oldTarget = clone $target;

        $target->starts_at = $startsAt;
        $target->ends_at = $endsAt;

        if (isset($options['timezone']) && is_string($options['timezone']) && $options['timezone'] !== '') {
            $target->timezone = $options['timezone'];
        }

        $target->rescheduled_at = CarbonImmutable::now();

        $currentStatus = (string) $target->status->getValue();

        if ($currentStatus === 'rescheduled') {
            $target->save();
        } else {
            if ($currentStatus === 'live') {
                throw new InvalidArgumentException('Live occurrences and sessions cannot be rescheduled.');
            }

            $target->status->transitionTo(OccurrenceRescheduledState::class);
        }

        $this->recordChange($target, 'rescheduled', null, [
            'old_starts_at' => $oldTarget->starts_at,
            'old_ends_at' => $oldTarget->ends_at,
            'new_starts_at' => $startsAt,
            'new_ends_at' => $endsAt,
        ]);

        if ($target instanceof EventOccurrence) {
            event(new EventOccurrenceRescheduled($oldTarget, $target));
        } elseif ($target instanceof EventSession) {
            event(new EventSessionRescheduled($oldTarget, $target));
        }

        return $target;
    }

    public function complete(Event | EventOccurrence | EventSession $target): void
    {
        if ($target instanceof Event) {
            $this->guardEvent($target);
        } else {
            $this->guardChild($target);
        }

        $target->completed_at = CarbonImmutable::now();

        $target->status->transitionTo(
            $target instanceof Event
                ? EventCompletedState::class
                : OccurrenceCompletedState::class,
        );

        $this->recordChange($target, 'completed');

        if ($target instanceof EventOccurrence) {
            event(new EventOccurrenceCompleted($target));
        } elseif ($target instanceof EventSession) {
            event(new EventSessionCompleted($target));
        }
    }

    public function archive(Event | EventOccurrence $target, ?string $reason = null): void
    {
        if ($target instanceof Event) {
            $this->guardEvent($target);
        } else {
            $this->guardChild($target);
        }

        $target->archived_at = CarbonImmutable::now();
        $target->status_reason = $reason;

        $target->status->transitionTo(
            $target instanceof Event
                ? EventArchivedState::class
                : OccurrenceArchivedState::class,
        );

        $this->recordChange($target, 'archived', $reason);

        event(new EventArchived($target, $reason));
    }

    private function guardEvent(Event $event): void
    {
        $key = $event->getKey();

        if ($key === null || ! $event->exists) {
            throw new InvalidArgumentException('Lifecycle actions require a persisted event.');
        }

        $persisted = EventWriteGuard::findOrFail($key);

        $event->setRawAttributes($persisted->getAttributes(), true);
        $event->setRelations([]);
        $event->exists = true;
    }

    private function guardChild(EventOccurrence | EventSession $target): void
    {
        $key = $target->getKey();

        if ($key === null || ! $target->exists) {
            throw new InvalidArgumentException('Lifecycle actions require a persisted occurrence or session.');
        }

        $persisted = $target::query()->withoutOwnerScope()->whereKey($key)->firstOrFail();

        EventWriteGuard::findOrFail($persisted->event_id);

        $target->setRawAttributes($persisted->getAttributes(), true);
        $target->setRelations([]);
        $target->exists = true;
    }

    private function recordChange(Event | EventOccurrence | EventSession $target, string $changeType, ?string $reason = null, array $context = []): void
    {
        $event = $this->eventForTarget($target);

        DispatchEventChangeChainAction::run(
            eventId: $event->getKey(),
            changeType: $changeType,
            changeCategory: $this->changeCategory($changeType),
            impactLevel: $this->impactLevel($changeType),
            requiresNotification: $this->requiresNotification($changeType),
            reason: $reason,
            occurrenceId: $this->occurrenceIdForTarget($target),
            sessionId: $this->sessionIdForTarget($target),
            oldValue: $context,
        );
    }

    private function eventForTarget(Event | EventOccurrence | EventSession $target): Event
    {
        return $target instanceof Event ? $target : $target->event;
    }

    private function occurrenceIdForTarget(Event | EventOccurrence | EventSession $target): ?string
    {
        if ($target instanceof EventOccurrence) {
            return $target->getKey();
        }

        if ($target instanceof EventSession) {
            return $target->event_occurrence_id;
        }

        return null;
    }

    private function sessionIdForTarget(Event | EventOccurrence | EventSession $target): ?string
    {
        return $target instanceof EventSession ? $target->getKey() : null;
    }

    private function changeCategory(string $changeType): string
    {
        return match ($changeType) {
            'published' => 'administration',
            'cancelled', 'postponed', 'rescheduled', 'delayed' => 'status',
            'completed', 'archived' => 'administration',
            default => 'administration',
        };
    }

    private function impactLevel(string $changeType): string
    {
        return match ($changeType) {
            'cancelled', 'postponed' => 'critical',
            'rescheduled', 'delayed' => 'high',
            'published' => 'low',
            'completed' => 'low',
            'archived' => 'medium',
            default => 'low',
        };
    }

    private function requiresNotification(string $changeType): bool
    {
        return in_array($changeType, ['cancelled', 'postponed', 'rescheduled'], true);
    }
}
