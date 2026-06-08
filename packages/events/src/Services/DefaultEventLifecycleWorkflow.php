<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Events\EventCancelled;
use AIArmada\Events\Events\EventDelayed;
use AIArmada\Events\Events\EventPostponed;
use AIArmada\Events\Events\EventResumed;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Support\EventLifecyclePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DefaultEventLifecycleWorkflow implements EventLifecycleWorkflow
{
    public function postpone(Event $event, ?Model $actor = null, ?string $note = null): Event
    {
        $this->assertCanTransition('postpone', $event, $note);

        return DB::transaction(function () use ($event, $actor, $note): Event {
            $this->stampStateChange($event, $actor, $note);

            $event->forceFill([
                'status' => EventStatus::Postponed,
                'postponed_at' => Carbon::now(),
            ])->save();

            $fresh = $event->refresh();

            DB::afterCommit(static fn () => event(new EventPostponed($fresh, $actor, $note)));

            return $fresh;
        });
    }

    public function delay(Event $event, ?Model $actor = null, ?string $note = null): Event
    {
        $this->assertCanTransition('delay', $event, $note);

        return DB::transaction(function () use ($event, $actor, $note): Event {
            $this->stampStateChange($event, $actor, $note);

            $event->forceFill([
                'status' => EventStatus::Delayed,
                'delayed_at' => Carbon::now(),
            ])->save();

            $fresh = $event->refresh();

            DB::afterCommit(static fn () => event(new EventDelayed($fresh, $actor, $note)));

            return $fresh;
        });
    }

    public function resume(Event $event, ?Model $actor = null, ?string $note = null): Event
    {
        $this->assertCanTransition('resume', $event, $note);

        return DB::transaction(function () use ($event, $actor, $note): Event {
            $this->stampStateChange($event, $actor, $note);

            $event->forceFill([
                'status' => EventStatus::Active,
            ])->save();

            $fresh = $event->refresh();

            DB::afterCommit(static fn () => event(new EventResumed($fresh, $actor, $note)));

            return $fresh;
        });
    }

    public function cancel(Event $event, ?Model $actor = null, ?string $note = null, ?string $reason = null): Event
    {
        $this->assertCanTransition('cancel', $event, $note);

        return DB::transaction(function () use ($event, $actor, $note, $reason): Event {
            $this->stampStateChange($event, $actor, $note);

            $event->forceFill([
                'status' => EventStatus::Cancelled,
                'cancelled_at' => Carbon::now(),
            ])->save();

            $fresh = $event->refresh();

            DB::afterCommit(static fn () => event(new EventCancelled($fresh, $actor, $note, $reason)));

            return $fresh;
        });
    }

    private function assertCanTransition(string $actionKey, Event $event, ?string $note): void
    {
        $current = $event->status instanceof EventStatus
            ? $event->status
            : EventStatus::Draft;

        $target = EventLifecyclePolicy::targetStatusFor($actionKey);

        if ($target === null) {
            throw new InvalidArgumentException(sprintf('Unknown lifecycle action [%s].', $actionKey));
        }

        if (! EventLifecyclePolicy::canTransition($actionKey, $current, $target)) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] lifecycle action is not allowed from [%s].',
                $actionKey,
                $current->value,
            ));
        }

        if (EventLifecyclePolicy::noteRequired($actionKey) && ($note === null || mb_trim($note) === '')) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] lifecycle action requires a note.',
                $actionKey,
            ));
        }
    }

    private function stampStateChange(Event $event, ?Model $actor, ?string $note): void
    {
        $event->forceFill([
            'last_state_change_actor_type' => $actor?->getMorphClass(),
            'last_state_change_actor_id' => $actor !== null ? (string) $actor->getKey() : null,
            'last_state_change_note' => $note,
            'last_state_change_at' => Carbon::now(),
        ])->save();
    }
}
