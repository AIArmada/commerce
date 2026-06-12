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
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class DefaultEventLifecycleWorkflow implements EventLifecycleWorkflow
{
    public function publish(Event $event): void
    {
        $event->update([
            'status' => Event::PUBLISHED,
            'published_at' => CarbonImmutable::now(),
        ]);

        $this->recordChange($event, 'published');

        event(new EventPublished($event));
    }

    public function cancel(Event|EventOccurrence $target, ?string $reason = null): void
    {
        $target->update([
            'status' => 'cancelled',
            'cancelled_at' => CarbonImmutable::now(),
            'status_reason' => $reason,
        ]);

        $this->recordChange($target, 'cancelled', $reason);

        if ($target instanceof Event) {
            event(new EventCancelled($target, $reason));
        } else {
            event(new EventOccurrenceCancelled($target, $reason));
        }
    }

    public function postpone(Event|EventOccurrence $target, ?string $reason = null): void
    {
        $target->update([
            'status' => 'postponed',
            'postponed_at' => CarbonImmutable::now(),
            'status_reason' => $reason,
        ]);

        $this->recordChange($target, 'postponed', $reason);

        if ($target instanceof Event) {
            event(new EventPostponed($target, $reason));
        } else {
            event(new EventOccurrencePostponed($target, $reason));
        }
    }

    public function delay(EventOccurrence $occurrence, ?string $reason = null, ?DateTimeInterface $expectedStartsAt = null): void
    {
        $occurrence->update([
            'status' => 'delayed',
            'status_reason' => $reason,
        ]);

        $this->recordChange($occurrence, 'delayed', $reason);

        event(new EventDelayed($occurrence, $reason, $expectedStartsAt));
    }

    public function reschedule(EventOccurrence $occurrence, DateTimeInterface $newStartsAt, DateTimeInterface $newEndsAt, array $options = []): EventOccurrence
    {
        $oldOccurrence = clone $occurrence;

        $occurrence->update([
            'starts_at' => $newStartsAt,
            'ends_at' => $newEndsAt,
            'status' => 'rescheduled',
        ]);

        $this->recordChange($occurrence, 'rescheduled', null, [
            'old_starts_at' => $oldOccurrence->starts_at,
            'old_ends_at' => $oldOccurrence->ends_at,
            'new_starts_at' => $newStartsAt,
            'new_ends_at' => $newEndsAt,
        ]);

        event(new EventOccurrenceRescheduled($oldOccurrence, $occurrence));

        return $occurrence;
    }

    public function complete(Event|EventOccurrence $target): void
    {
        $target->update([
            'status' => 'completed',
            'completed_at' => CarbonImmutable::now(),
        ]);

        $this->recordChange($target, 'completed');

        if ($target instanceof EventOccurrence) {
            event(new EventOccurrenceCompleted($target));
        }
    }

    public function archive(Event|EventOccurrence $target, ?string $reason = null): void
    {
        $target->update([
            'status' => 'archived',
            'archived_at' => CarbonImmutable::now(),
            'status_reason' => $reason,
        ]);

        $this->recordChange($target, 'archived', $reason);

        event(new EventArchived($target, $reason));
    }

    private function recordChange(Event|EventOccurrence $target, string $changeType, ?string $reason = null, array $context = []): void
    {
        $event = $target instanceof Event ? $target : $target->event;

        DispatchEventChangeChainAction::run(
            eventId: $event->getKey(),
            changeType: $changeType,
            changeCategory: $this->changeCategory($changeType),
            impactLevel: $this->impactLevel($changeType),
            requiresNotification: $this->requiresNotification($changeType),
            reason: $reason,
            occurrenceId: $target instanceof EventOccurrence ? $target->getKey() : null,
            oldValue: $context,
        );
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
