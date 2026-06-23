<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\CloneEventSessionAction;
use AIArmada\Events\Actions\CreateEventSessionAction;
use AIArmada\Events\Actions\DeleteEventSessionAction;
use AIArmada\Events\Actions\UpdateEventSessionAction;
use AIArmada\Events\Events\EventSessionCancelled;
use AIArmada\Events\Events\EventSessionCompleted;
use AIArmada\Events\Events\EventSessionCreated;
use AIArmada\Events\Events\EventSessionDelayed;
use AIArmada\Events\Events\EventSessionDeleted;
use AIArmada\Events\Events\EventSessionPostponed;
use AIArmada\Events\Events\EventSessionRescheduled;
use AIArmada\Events\Events\EventSessionUpdated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    $this->event = Event::factory()->create();
    $this->occurrence = EventOccurrence::factory()->create([
        'event_id' => $this->event->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
});

it('creates a session under an occurrence', function (): void {
    Illuminate\Support\Facades\Event::fake([EventSessionCreated::class]);

    $session = app(CreateEventSessionAction::class)->handle(
        $this->occurrence,
        [
            'title' => 'Keynote Address',
            'starts_at' => '2026-07-01 09:00:00',
            'ends_at' => '2026-07-01 10:30:00',
            'capacity' => 100,
        ],
    );

    expect($session)
        ->exists->toBeTrue()
        ->title->toBe('Keynote Address')
        ->event_id->toBe($this->event->id)
        ->event_occurrence_id->toBe($this->occurrence->id)
        ->capacity->toBe(100);

    expect($session->status->getValue())->toBe('scheduled');

    Illuminate\Support\Facades\Event::assertDispatched(EventSessionCreated::class, fn (EventSessionCreated $e) => $e->session->is($session));
});

it('normalizes session content when creating a session', function (): void {
    $session = app(CreateEventSessionAction::class)->handle(
        $this->occurrence,
        [
            'title' => '  Opening   Keynote  ',
            'summary' => '   ',
            'description' => '  Opening keynote details  ',
            'starts_at' => '2026-07-01 09:00:00',
            'ends_at' => '2026-07-01 10:30:00',
        ],
    );

    expect($session->title)->toBe('Opening Keynote')
        ->and($session->slug)->toBe('opening-keynote')
        ->and($session->summary)->toBeNull()
        ->and($session->description)->toBe('Opening keynote details');
});

it('creates a session with auto-generated slug and sort order', function (): void {
    EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'sort_order' => 5,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $session = app(CreateEventSessionAction::class)->handle(
        $this->occurrence,
        ['title' => 'Workshop B'],
    );

    expect($session->slug)->toBe('workshop-b')
        ->and($session->sort_order)->toBe(6);
});

it('generates a slug when the slug input is blank', function (): void {
    $session = app(CreateEventSessionAction::class)->handle(
        $this->occurrence,
        [
            'title' => 'Workshop C',
            'slug' => '',
            'starts_at' => '2026-07-01 09:00:00',
            'ends_at' => '2026-07-01 10:00:00',
        ],
    );

    expect($session->slug)->toBe('workshop-c');
});

it('validates session title is required', function (): void {
    app(CreateEventSessionAction::class)->handle($this->occurrence, []);
})->throws(InvalidArgumentException::class, 'Session title is required.');

it('validates session end time is after start time', function (): void {
    app(CreateEventSessionAction::class)->handle(
        $this->occurrence,
        [
            'title' => 'Bad Session',
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 09:00:00',
        ],
    );
})->throws(InvalidArgumentException::class, 'Session end time must be after the start time.');

it('updates a session and dispatches updated event', function (): void {
    Illuminate\Support\Facades\Event::fake([EventSessionUpdated::class]);

    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'title' => 'Original Title',
        'capacity' => 50,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $result = app(UpdateEventSessionAction::class)->handle($session, [
        'title' => 'Updated Title',
        'capacity' => 100,
    ]);

    expect($result['changes'])->toHaveKeys(['title', 'capacity'])
        ->and($result['session']->title)->toBe('Updated Title')
        ->and($result['session']->capacity)->toBe(100);

    Illuminate\Support\Facades\Event::assertDispatched(EventSessionUpdated::class);
});

it('tracks status changes via change chain', function (): void {
    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'status' => 'scheduled',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    app(UpdateEventSessionAction::class)->handle($session, [
        'status' => 'cancelled',
        'status_reason' => 'Speaker unavailable',
    ]);

    expect($session->fresh()->changeLogs)->toHaveCount(1)
        ->and($session->fresh()->cancelled_at)->not->toBeNull()
        ->and($session->fresh()->status->getValue())->toBe('cancelled');
});

it('tracks rescheduled status changes via change chain and timestamps', function (): void {
    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'status' => 'scheduled',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    app(UpdateEventSessionAction::class)->handle($session, [
        'status' => 'rescheduled',
    ]);

    expect($session->fresh()->changeLogs)->toHaveCount(1)
        ->and($session->fresh()->rescheduled_at)->not->toBeNull()
        ->and($session->fresh()->status->getValue())->toBe('rescheduled');
});

it('does not dispatch change chain for non-status updates', function (): void {
    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    app(UpdateEventSessionAction::class)->handle($session, [
        'summary' => 'New summary text',
    ]);

    expect($session->fresh()->changeLogs)->toHaveCount(0);
});

it('normalizes editable session content during updates', function (): void {
    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'title' => 'Original Title',
        'summary' => 'Original summary',
        'description' => 'Original description',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $result = app(UpdateEventSessionAction::class)->handle($session, [
        'title' => '  Closing   Panel  ',
        'summary' => '   ',
        'description' => '  Updated description  ',
    ]);

    expect($result['session']->title)->toBe('Closing Panel')
        ->and($result['session']->summary)->toBeNull()
        ->and($result['session']->description)->toBe('Updated description');
});

it('rejects blank session titles during updates', function (): void {
    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'title' => 'Original Title',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    app(UpdateEventSessionAction::class)->handle($session, [
        'title' => '   ',
    ]);
})->throws(InvalidArgumentException::class, 'Session title is required.');

it('supports lifecycle transitions on sessions', function (): void {
    $startsAt = CarbonImmutable::parse('2026-07-01 09:00:00');
    $endsAt = CarbonImmutable::parse('2026-07-01 10:00:00');
    Illuminate\Support\Facades\Event::fake([
        EventSessionCancelled::class,
        EventSessionCompleted::class,
        EventSessionDelayed::class,
        EventSessionPostponed::class,
        EventSessionRescheduled::class,
    ]);

    $createSession = function () use ($startsAt, $endsAt): EventSession {
        return EventSession::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence->id,
            'status' => 'published',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    };

    $delayed = $createSession();
    $delayed->delay('Traffic on the route', $startsAt->addHour());

    expect($delayed->fresh()->status->getValue())->toBe('delayed')
        ->and($delayed->fresh()->delayed_at)->not->toBeNull()
        ->and($delayed->fresh()->changeLogs)->toHaveCount(1);
    Illuminate\Support\Facades\Event::assertDispatched(
        EventSessionDelayed::class,
        fn (EventSessionDelayed $event): bool => $event->session->is($delayed)
            && $event->expectedStartsAt?->toDateTimeString() === $startsAt->addHour()->toDateTimeString(),
    );

    $postponed = $createSession();
    $postponed->postpone('Venue unavailable');

    expect($postponed->fresh()->status->getValue())->toBe('postponed')
        ->and($postponed->fresh()->postponed_at)->not->toBeNull()
        ->and($postponed->fresh()->changeLogs)->toHaveCount(1);
    Illuminate\Support\Facades\Event::assertDispatched(
        EventSessionPostponed::class,
        fn (EventSessionPostponed $event): bool => $event->session->is($postponed),
    );

    $cancelled = $createSession();
    $cancelled->cancel('Speaker unavailable');

    expect($cancelled->fresh()->status->getValue())->toBe('cancelled')
        ->and($cancelled->fresh()->cancelled_at)->not->toBeNull()
        ->and($cancelled->fresh()->changeLogs)->toHaveCount(1);
    Illuminate\Support\Facades\Event::assertDispatched(
        EventSessionCancelled::class,
        fn (EventSessionCancelled $event): bool => $event->session->is($cancelled),
    );

    $completed = $createSession();
    $completed->complete();

    expect($completed->fresh()->status->getValue())->toBe('completed')
        ->and($completed->fresh()->completed_at)->not->toBeNull()
        ->and($completed->fresh()->changeLogs)->toHaveCount(1);
    Illuminate\Support\Facades\Event::assertDispatched(
        EventSessionCompleted::class,
        fn (EventSessionCompleted $event): bool => $event->session->is($completed),
    );

    $rescheduled = $createSession();
    $rescheduled->reschedule($startsAt->addDay(), $endsAt->addDay());

    expect($rescheduled->fresh()->status->getValue())->toBe('rescheduled')
        ->and($rescheduled->fresh()->starts_at)->toEqual($startsAt->addDay())
        ->and($rescheduled->fresh()->ends_at)->toEqual($endsAt->addDay())
        ->and($rescheduled->fresh()->rescheduled_at)->not->toBeNull()
        ->and($rescheduled->fresh()->changeLogs)->toHaveCount(1);
    Illuminate\Support\Facades\Event::assertDispatched(
        EventSessionRescheduled::class,
        fn (EventSessionRescheduled $event): bool => $event->oldSession->starts_at?->toDateTimeString() === $startsAt->toDateTimeString()
            && $event->newSession->starts_at?->toDateTimeString() === $startsAt->addDay()->toDateTimeString(),
    );
});

it('archives a session on delete', function (): void {
    Illuminate\Support\Facades\Event::fake([EventSessionDeleted::class]);

    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    app(DeleteEventSessionAction::class)->handle($session);

    $fresh = $session->fresh();
    expect($fresh->status->getValue())->toBe('archived')
        ->and($fresh->archived_at)->not->toBeNull();

    Illuminate\Support\Facades\Event::assertDispatched(EventSessionDeleted::class, fn (EventSessionDeleted $e) => $e->session->is($session));
});

it('clones a session with new slug', function (): void {
    Illuminate\Support\Facades\Event::fake([EventSessionCreated::class]);

    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'title' => 'Original Session',
        'slug' => 'original-session',
        'capacity' => 50,
        'sort_order' => 3,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $clone = app(CloneEventSessionAction::class)->handle($session);

    expect($clone)
        ->exists->toBeTrue()
        ->title->toBe('Original Session (Copy)')
        ->slug->not->toBe('original-session')
        ->capacity->toBe(50)
        ->event_id->toBe($this->event->id)
        ->starts_at->toEqual($session->starts_at)
        ->ends_at->toEqual($session->ends_at)
        ->id->not->toBe($session->id);

    expect($clone->status->getValue())->toBe('scheduled');

    Illuminate\Support\Facades\Event::assertDispatched(EventSessionCreated::class, fn (EventSessionCreated $e) => $e->session->is($clone));
});

it('clones a session with overridden attributes', function (): void {
    $startsAt = CarbonImmutable::parse('2026-07-01 09:00:00');
    $endsAt = CarbonImmutable::parse('2026-07-01 10:00:00');

    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'title' => 'Original',
        'capacity' => 50,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);

    $clone = app(CloneEventSessionAction::class)->handle($session, [
        'title' => 'Custom Clone',
        'capacity' => 200,
        'starts_at' => $startsAt->addDay(),
        'ends_at' => $endsAt->addDay(),
    ]);

    expect($clone)
        ->title->toBe('Custom Clone')
        ->capacity->toBe(200)
        ->starts_at->toEqual($startsAt->addDay());
});

it('normalizes cloned session content', function (): void {
    $session = EventSession::factory()->create([
        'event_id' => $this->event->id,
        'event_occurrence_id' => $this->occurrence->id,
        'title' => 'Original',
        'summary' => 'Original summary',
        'description' => 'Original description',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $clone = app(CloneEventSessionAction::class)->handle($session, [
        'title' => '  Panel   Discussion  ',
        'slug' => 'panel-discussion-copy',
        'summary' => '  <strong>Panel summary</strong>  ',
        'description' => '  Panel details  ',
    ]);

    expect($clone)
        ->title->toBe('Panel Discussion')
        ->slug->toBe('panel-discussion-copy')
        ->summary->toBe('Panel summary')
        ->description->toBe('Panel details');
});

it('rejects cross-tenant session writes', function (): void {
    $originalOwnerEnabled = config('events.features.owner.enabled');
    $originalOwnerIncludeGlobal = config('events.features.owner.include_global');
    $originalOwnerAutoAssign = config('events.features.owner.auto_assign_on_create');

    config()->set('events.features.owner.enabled', true);
    config()->set('events.features.owner.include_global', false);
    config()->set('events.features.owner.auto_assign_on_create', true);

    try {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $occurrence = OwnerContext::withOwner($ownerA, function (): EventOccurrence {
            $event = Event::factory()->create();

            return EventOccurrence::factory()->create([
                'event_id' => $event->id,
                'timezone' => 'UTC',
                'delivery_mode' => 'in_person',
            ]);
        });

        $session = OwnerContext::withOwner($ownerA, function () use ($occurrence): EventSession {
            return app(CreateEventSessionAction::class)->handle($occurrence, [
                'title' => 'Owner A Session',
            ]);
        });

        expect(function () use ($ownerB, $occurrence): void {
            OwnerContext::withOwner($ownerB, function () use ($occurrence): void {
                app(CreateEventSessionAction::class)->handle($occurrence, [
                    'title' => 'Cross Tenant Session',
                ]);
            });
        })->toThrow(AuthorizationException::class);

        expect(function () use ($ownerB, $session): void {
            OwnerContext::withOwner($ownerB, function () use ($session): void {
                app(UpdateEventSessionAction::class)->handle($session, [
                    'title' => 'Cross Tenant Update',
                ]);
            });
        })->toThrow(AuthorizationException::class);

        expect(function () use ($ownerB, $session): void {
            OwnerContext::withOwner($ownerB, function () use ($session): void {
                app(CloneEventSessionAction::class)->handle($session, [
                    'title' => 'Cross Tenant Clone',
                ]);
            });
        })->toThrow(AuthorizationException::class);

        expect(function () use ($ownerB, $session): void {
            OwnerContext::withOwner($ownerB, function () use ($session): void {
                app(DeleteEventSessionAction::class)->handle($session);
            });
        })->toThrow(AuthorizationException::class);
    } finally {
        config()->set('events.features.owner.enabled', $originalOwnerEnabled);
        config()->set('events.features.owner.include_global', $originalOwnerIncludeGlobal);
        config()->set('events.features.owner.auto_assign_on_create', $originalOwnerAutoAssign);
    }
});

it('ignores direct event reassignment when updating a session', function (): void {
    $eventA = Event::factory()->create();
    $occurrenceA = EventOccurrence::factory()->create([
        'event_id' => $eventA->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $eventB = Event::factory()->create();
    $occurrenceB = EventOccurrence::factory()->create([
        'event_id' => $eventB->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);

    $session = app(CreateEventSessionAction::class)->handle($occurrenceA, [
        'title' => 'Original Session',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 10:00:00',
    ]);

    $result = app(UpdateEventSessionAction::class)->handle($session, [
        'title' => 'Retitled Session',
        'event_id' => $eventB->id,
        'event_occurrence_id' => $occurrenceB->id,
    ]);

    expect($result['session']->event_id)->toBe($eventA->id)
        ->and($result['session']->event_occurrence_id)->toBe($occurrenceA->id)
        ->and($result['changes'])->toHaveKey('title')
        ->and($result['changes'])->not->toHaveKey('event_id')
        ->and($result['changes'])->not->toHaveKey('event_occurrence_id');
});
