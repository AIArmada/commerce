<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Actions\RecordEventEngagementAction;
use AIArmada\Events\Enums\EventEngagementType;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Events\EventCancelled;
use AIArmada\Events\Events\EventDelayed;
use AIArmada\Events\Events\EventPostponed;
use AIArmada\Events\Events\EventResumed;
use AIArmada\Events\Exceptions\InvalidEventStatusTransition;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Services\DefaultEventLifecycleWorkflow;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Events\Support\EventLifecyclePolicy;
use Illuminate\Support\Facades\Event as EventFacade;

it('enumerates the six lifecycle states with correct helpers', function (): void {
    $cases = [
        EventStatus::Draft->value => false,
        EventStatus::Active->value => true,
        EventStatus::Postponed->value => true,
        EventStatus::Delayed->value => true,
        EventStatus::Cancelled->value => true,
        EventStatus::Archived->value => true,
    ];

    foreach ($cases as $value => $expected) {
        $status = EventStatus::from($value);
        expect($status->isPubliclyVisible())->toBe($expected)
            ->and($status->isEngageable())->toBe($status === EventStatus::Active);
    }

    expect(EventStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(EventStatus::Archived->isTerminal())->toBeTrue()
        ->and(EventStatus::Postponed->isRecoverable())->toBeTrue()
        ->and(EventStatus::Delayed->isRecoverable())->toBeTrue()
        ->and(EventStatus::Active->isRecoverable())->toBeFalse()
        ->and(EventStatus::Cancelled->isRecoverable())->toBeFalse();
});

it('exposes a precise 11-pair transition matrix', function (): void {
    $cases = EventStatus::cases();

    foreach ($cases as $from) {
        foreach ($cases as $to) {
            $expected = match ([$from, $to]) {
                [EventStatus::Draft, EventStatus::Active],
                [EventStatus::Draft, EventStatus::Archived],
                [EventStatus::Active, EventStatus::Postponed],
                [EventStatus::Active, EventStatus::Delayed],
                [EventStatus::Active, EventStatus::Cancelled],
                [EventStatus::Active, EventStatus::Archived],
                [EventStatus::Postponed, EventStatus::Active],
                [EventStatus::Postponed, EventStatus::Cancelled],
                [EventStatus::Delayed, EventStatus::Active],
                [EventStatus::Delayed, EventStatus::Postponed],
                [EventStatus::Delayed, EventStatus::Cancelled] => true,
                default => false,
            };

            expect($from->canTransitionTo($to))->toBe(
                $expected,
                sprintf('Expected canTransitionTo from [%s] to [%s] to be %s.', $from->value, $to->value, $expected ? 'true' : 'false'),
            );
        }
    }
});

it('refuses illegal state transitions in the model boot hook', function (): void {
    $event = EventModel::query()->create([
        'name' => 'State Transition Event',
        'slug' => 'state-transition-event-' . uniqid(),
        'status' => EventStatus::Cancelled,
        'default_timezone' => 'UTC',
    ]);

    $event->status = EventStatus::Active;

    expect(fn () => $event->save())
        ->toThrow(InvalidEventStatusTransition::class);

    $event->refresh();

    expect($event->status)->toBe(EventStatus::Cancelled);
});

it('allows legal transitions in the model boot hook', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Legal Transition Event',
        'slug' => 'legal-transition-event-' . uniqid(),
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    $event->status = EventStatus::Postponed;
    $event->save();
    expect($event->fresh()->status)->toBe(EventStatus::Postponed);

    $event->status = EventStatus::Active;
    $event->save();
    expect($event->fresh()->status)->toBe(EventStatus::Active);
});

it('exposes the state change audit columns', function (): void {
    expect(Schema::hasColumn('events', 'cancelled_at'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'postponed_at'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'delayed_at'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'last_state_change_actor_type'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'last_state_change_actor_id'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'last_state_change_note'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'last_state_change_at'))->toBeTrue();
});

it('runs the postpone action and emits EventPostponed', function (): void {
    EventFacade::fake([EventPostponed::class]);

    $event = EventModel::query()->create([
        'name' => 'Postpone Action',
        'slug' => 'postpone-action-' . uniqid(),
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    $actor = User::query()->create(['name' => 'Moderator', 'email' => 'mod-' . uniqid() . '@example.com', 'password' => 'secret']);
    $workflow = app(DefaultEventLifecycleWorkflow::class);

    $workflow->postpone($event, $actor, 'Speaker is sick');

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Postponed)
        ->and($event->postponed_at)->not->toBeNull()
        ->and($event->last_state_change_note)->toBe('Speaker is sick')
        ->and($event->last_state_change_actor_id)->toBe($actor->getKey());

    EventFacade::assertDispatched(EventPostponed::class);
});

it('runs the delay action, requires a note, and emits EventDelayed', function (): void {
    EventFacade::fake([EventDelayed::class]);

    $event = EventModel::query()->create([
        'name' => 'Delay Action',
        'slug' => 'delay-action-' . uniqid(),
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    $workflow = app(DefaultEventLifecycleWorkflow::class);

    expect(fn () => $workflow->delay($event, null, null))
        ->toThrow(InvalidArgumentException::class, 'requires a note');

    $workflow->delay($event, null, 'Speaker running late');

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Delayed)
        ->and($event->delayed_at)->not->toBeNull()
        ->and($event->last_state_change_note)->toBe('Speaker running late');

    EventFacade::assertDispatched(EventDelayed::class);
});

it('runs the resume action and emits EventResumed', function (): void {
    EventFacade::fake([EventPostponed::class, EventResumed::class]);

    $event = EventModel::query()->create([
        'name' => 'Resume Action',
        'slug' => 'resume-action-' . uniqid(),
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    $workflow = app(DefaultEventLifecycleWorkflow::class);
    $workflow->postpone($event, null, 'Speaker sick');

    expect($event->fresh()->postponed_at)->not->toBeNull();

    $workflow->resume($event->fresh(), null, 'New time confirmed');

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Active)
        ->and($event->postponed_at)->not->toBeNull(); // preserved for audit

    EventFacade::assertDispatched(EventResumed::class);
});

it('runs the cancel action and emits EventCancelled', function (): void {
    EventFacade::fake([EventCancelled::class]);

    $event = EventModel::query()->create([
        'name' => 'Cancel Action',
        'slug' => 'cancel-action-' . uniqid(),
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    $workflow = app(DefaultEventLifecycleWorkflow::class);
    $workflow->cancel($event, null, 'Weather emergency', 'storm');

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Cancelled)
        ->and($event->cancelled_at)->not->toBeNull();

    EventFacade::assertDispatched(EventCancelled::class);
});

it('refuses to transition a cancelled event back to active', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Terminal Event',
        'slug' => 'terminal-event-' . uniqid(),
        'status' => EventStatus::Cancelled,
        'default_timezone' => 'UTC',
    ]);

    expect(fn () => $event->update(['status' => EventStatus::Active]))
        ->toThrow(InvalidEventStatusTransition::class);
});

it('refuses engagement recording when the event is not engageable', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Delayed Engagement Event',
        'slug' => 'delayed-engagement-' . uniqid(),
        'status' => EventStatus::Delayed,
        'default_timezone' => 'UTC',
    ]);

    $actor = User::query()->create(['name' => 'Saver', 'email' => 'saver-' . uniqid() . '@example.com', 'password' => 'secret']);

    expect(fn () => app(RecordEventEngagementAction::class)->handle(
        $event,
        EventEngagementType::Saved,
        $actor,
    ))->toThrow(InvalidArgumentException::class, 'not currently engageable');
});

it('refuses registration when the parent event is not engageable', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Postponed Registration Event',
        'slug' => 'postponed-registration-' . uniqid(),
        'status' => EventStatus::Postponed,
        'default_timezone' => 'UTC',
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now('UTC')->addDay(),
        'timezone' => 'UTC',
    ]);

    expect(fn () => app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'Late Registrant',
        'email' => 'late-' . uniqid() . '@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'is [postponed]');
});

it('includes all five non-Active states in publiclyAccessible but only Active in publiclyDiscoverable', function (): void {
    $statuses = [
        EventStatus::Active->value,
        EventStatus::Postponed->value,
        EventStatus::Delayed->value,
        EventStatus::Cancelled->value,
        EventStatus::Archived->value,
    ];

    foreach ($statuses as $status) {
        $event = EventModel::query()->create([
            'name' => "PA {$status}",
            'slug' => "pa-{$status}-" . uniqid(),
            'status' => $status,
            'moderation_status' => EventModerationStatus::Approved,
            'visibility' => EventVisibility::Public,
            'default_timezone' => 'UTC',
        ]);

        expect(EventModel::query()->publiclyAccessible()->whereKey($event->id)->exists())->toBeTrue();
    }

    $active = EventModel::query()->create([
        'name' => 'PD Active',
        'slug' => 'pd-active-' . uniqid(),
        'status' => EventStatus::Active,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'default_timezone' => 'UTC',
    ]);

    $postponed = EventModel::query()->create([
        'name' => 'PD Postponed',
        'slug' => 'pd-postponed-' . uniqid(),
        'status' => EventStatus::Postponed,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'default_timezone' => 'UTC',
    ]);

    expect(EventModel::query()->publiclyDiscoverable()->whereKey($active->id)->exists())->toBeTrue()
        ->and(EventModel::query()->publiclyDiscoverable()->whereKey($postponed->id)->exists())->toBeFalse();
});

it('exposes the upcoming, live, and delayed scopes', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Upcoming Scope',
        'slug' => 'upcoming-scope-' . uniqid(),
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
        'public_starts_at' => now('UTC')->addDays(7),
    ]);

    $delayed = EventModel::query()->create([
        'name' => 'Delayed Scope',
        'slug' => 'delayed-scope-' . uniqid(),
        'status' => EventStatus::Delayed,
        'default_timezone' => 'UTC',
    ]);

    expect(EventModel::query()->upcoming()->whereKey($event->id)->exists())->toBeTrue()
        ->and(EventModel::query()->upcoming()->whereKey($delayed->id)->exists())->toBeFalse()
        ->and(EventModel::query()->delayed()->whereKey($delayed->id)->exists())->toBeTrue()
        ->and(EventModel::query()->delayed()->whereKey($event->id)->exists())->toBeFalse();
});

it('exposes the EventLifecyclePolicy transition rules', function (): void {
    expect(EventLifecyclePolicy::actionKeys())
        ->toContain('postpone', 'delay', 'resume', 'cancel');

    expect(EventLifecyclePolicy::noteRequired('delay'))->toBeTrue()
        ->and(EventLifecyclePolicy::noteRequired('postpone'))->toBeFalse()
        ->and(EventLifecyclePolicy::noteRequired('resume'))->toBeFalse()
        ->and(EventLifecyclePolicy::noteRequired('cancel'))->toBeFalse();

    expect(EventLifecyclePolicy::canTransition('postpone', EventStatus::Active, EventStatus::Postponed))->toBeTrue()
        ->and(EventLifecyclePolicy::canTransition('postpone', EventStatus::Active, EventStatus::Cancelled))->toBeFalse()
        ->and(EventLifecyclePolicy::canTransition('delay', EventStatus::Active, EventStatus::Delayed))->toBeTrue()
        ->and(EventLifecyclePolicy::canTransition('delay', EventStatus::Postponed, EventStatus::Delayed))->toBeFalse()
        ->and(EventLifecyclePolicy::canTransition('resume', EventStatus::Postponed, EventStatus::Active))->toBeTrue()
        ->and(EventLifecyclePolicy::canTransition('resume', EventStatus::Cancelled, EventStatus::Active))->toBeFalse()
        ->and(EventLifecyclePolicy::canTransition('cancel', EventStatus::Active, EventStatus::Cancelled))->toBeTrue()
        ->and(EventLifecyclePolicy::canTransition('cancel', EventStatus::Cancelled, EventStatus::Active))->toBeFalse();
});
