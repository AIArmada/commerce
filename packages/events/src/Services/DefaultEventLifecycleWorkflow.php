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
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class DefaultEventLifecycleWorkflow implements EventLifecycleWorkflow
{
    public function publish(Event $event): void
    {
        $event->published_at = CarbonImmutable::now();
        $event->status->transitionTo(EventPublishedState::class);

        $this->recordChange($event, 'published');

        event(new EventPublished($event));
    }

    public function cancel(Event | EventOccurrence | EventSession $target, ?string $reason = null): void
    {
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

    public function reschedule(EventOccurrence | EventSession $target, DateTimeInterface $newStartsAt, DateTimeInterface $newEndsAt, array $options = []): EventOccurrence | EventSession
    {
        $oldTarget = clone $target;

        $target->starts_at = CarbonImmutable::createFromInterface($newStartsAt);
        $target->ends_at = CarbonImmutable::createFromInterface($newEndsAt);
        $target->rescheduled_at = CarbonImmutable::now();
        $target->status->transitionTo(OccurrenceRescheduledState::class);

        $this->recordChange($target, 'rescheduled', null, [
            'old_starts_at' => $oldTarget->starts_at,
            'old_ends_at' => $oldTarget->ends_at,
            'new_starts_at' => $newStartsAt,
            'new_ends_at' => $newEndsAt,
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
