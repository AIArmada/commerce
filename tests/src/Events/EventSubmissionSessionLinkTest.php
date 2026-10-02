<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\Support\EventSubmissionOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', true);

    $this->owner = User::factory()->create();
    $this->other = User::factory()->create();

    OwnerContext::withOwner($this->owner, function (): void {
        $this->event = Event::factory()->create();
        $this->occurrence = EventOccurrence::factory()->create([
            'event_id' => $this->event->id,
            'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
            'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
        ]);
        $this->session = EventSession::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence->id,
            'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
            'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
        ]);
    });
});

// The target pair stays the owner target; it is never the submitted graph.
it('keeps target ownership semantics intact for submission writes', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        expect(fn () => EventSubmission::factory()->create([
            'target_type' => $this->other->getMorphClass(),
            'target_id' => $this->other->getKey(),
        ]))->toThrow(AuthorizationException::class, 'Cross-owner');

        expect(fn () => EventSubmission::factory()->create([
            'target_type' => $this->owner->getMorphClass(),
            'target_id' => null,
        ]))->toThrow(InvalidArgumentException::class, 'both be present or both be null');

        expect(fn () => EventSubmission::factory()->create([
            'target_type' => null,
            'target_id' => null,
        ]))->toThrow(AuthorizationException::class, 'Explicit global');
    });
});

it('rejects reassigning a persisted submission target', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $submission = EventSubmission::factory()->create([
            'target_type' => $this->owner->getMorphClass(),
            'target_id' => $this->owner->getKey(),
        ]);

        expect(fn () => $submission->update([
            'target_type' => $this->other->getMorphClass(),
            'target_id' => $this->other->getKey(),
        ]))->toThrow(InvalidArgumentException::class, 'cannot be reassigned');
    });
});

it('allows global submissions only in explicit global context', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $submission = EventSubmission::factory()->create([
            'target_type' => null,
            'target_id' => null,
        ]);

        expect($submission->exists)->toBeTrue();
    });
});

// Session linkage points at the submitted session, not the owner target.
it('links a submission to a session of the submitted event and occurrence', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $submission = EventSubmission::factory()->create([
            'target_type' => $this->owner->getMorphClass(),
            'target_id' => $this->owner->getKey(),
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence->id,
            'event_session_id' => $this->session->id,
        ]);

        expect($submission->session)->toBeInstanceOf(EventSession::class)
            ->and($submission->session->is($this->session))->toBeTrue()
            ->and($submission->target->is($this->owner))->toBeTrue()
            ->and($submission->event->is($this->event))->toBeTrue();
    });
});

it('rejects a session from another event or a missing session', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $otherEvent = Event::factory()->create();
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);
        $otherSession = EventSession::factory()->create([
            'event_id' => $otherEvent->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]);

        expect(fn () => EventSubmission::factory()->create([
            'event_id' => $this->event->id,
            'event_session_id' => $otherSession->id,
        ]))->toThrow(InvalidArgumentException::class, 'session of the submitted event');

        expect(fn () => EventSubmission::factory()->create([
            'event_id' => $this->event->id,
            'event_session_id' => (string) Str::uuid(),
        ]))->toThrow(InvalidArgumentException::class, 'existing session');
    });
});

it('rejects a session outside the submitted occurrence', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $this->event->id]);

        expect(fn () => EventSubmission::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => $otherOccurrence->id,
            'event_session_id' => $this->session->id,
        ]))->toThrow(InvalidArgumentException::class, 'session of the submitted occurrence');
    });
});

it('requires event_id when linking a session or occurrence', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        expect(fn () => EventSubmission::factory()->create([
            'target_type' => $this->owner->getMorphClass(),
            'target_id' => $this->owner->getKey(),
            'event_session_id' => $this->session->id,
        ]))->toThrow(InvalidArgumentException::class, 'must set event_id');

        expect(fn () => EventSubmission::factory()->create([
            'target_type' => $this->owner->getMorphClass(),
            'target_id' => $this->owner->getKey(),
            'event_occurrence_id' => $this->occurrence->id,
        ]))->toThrow(InvalidArgumentException::class, 'must set event_id');
    });
});

it('rejects cross-owner event-bound session links via the event guard', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $eventId = $this->event->id;
        $sessionId = $this->session->id;

        OwnerContext::withOwner($this->other, function () use ($eventId, $sessionId): void {
            expect(fn () => EventSubmission::factory()->create([
                'event_id' => $eventId,
                'event_session_id' => $sessionId,
            ]))->toThrow(AuthorizationException::class);
        });
    });
});

it('rejects an occurrence from another event even without a session', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $otherEvent = Event::factory()->create();
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);

        expect(fn () => EventSubmission::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => $otherOccurrence->id,
        ]))->toThrow(InvalidArgumentException::class, 'occurrence of the submitted event');

        expect(fn () => EventSubmission::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => (string) Str::uuid(),
        ]))->toThrow(InvalidArgumentException::class, 'existing occurrence');
    });
});

it('allows a null occurrence with a session and preserves the canonical null', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $submission = EventSubmission::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => null,
            'event_session_id' => $this->session->id,
        ]);

        expect($submission->event_occurrence_id)->toBeNull()
            ->and($submission->session->is($this->session))->toBeTrue();
    });
});

it('rejects session mismatch in explicit global context', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $eventA = Event::factory()->create();
        $occurrenceA = EventOccurrence::factory()->create(['event_id' => $eventA->id]);
        $sessionA = EventSession::factory()->create([
            'event_id' => $eventA->id,
            'event_occurrence_id' => $occurrenceA->id,
        ]);

        $eventB = Event::factory()->create();
        $occurrenceB = EventOccurrence::factory()->create(['event_id' => $eventB->id]);
        $sessionB = EventSession::factory()->create([
            'event_id' => $eventB->id,
            'event_occurrence_id' => $occurrenceB->id,
        ]);

        expect(fn () => EventSubmission::factory()->create([
            'event_id' => $eventA->id,
            'event_session_id' => $sessionB->id,
        ]))->toThrow(InvalidArgumentException::class, 'session of the submitted event');

        expect(fn () => EventSubmission::factory()->create([
            'event_id' => $eventA->id,
            'event_occurrence_id' => $occurrenceA->id,
            'event_session_id' => $sessionB->id,
        ]))->toThrow(InvalidArgumentException::class);
    });
});

it('rejects session mismatch when owner scope is disabled', function (): void {
    $original = config('events.features.owner.enabled');
    config()->set('events.features.owner.enabled', false);

    try {
        $eventA = Event::factory()->create();
        $occurrenceA = EventOccurrence::factory()->create(['event_id' => $eventA->id]);
        $sessionA = EventSession::factory()->create([
            'event_id' => $eventA->id,
            'event_occurrence_id' => $occurrenceA->id,
        ]);

        $eventB = Event::factory()->create();
        $occurrenceB = EventOccurrence::factory()->create(['event_id' => $eventB->id]);
        $sessionB = EventSession::factory()->create([
            'event_id' => $eventB->id,
            'event_occurrence_id' => $occurrenceB->id,
        ]);

        expect(fn () => EventSubmission::factory()->create([
            'event_id' => $eventA->id,
            'event_session_id' => $sessionB->id,
        ]))->toThrow(InvalidArgumentException::class, 'session of the submitted event');

        expect(fn () => EventSubmission::factory()->create([
            'event_session_id' => $sessionA->id,
        ]))->toThrow(InvalidArgumentException::class, 'must set event_id');
    } finally {
        config()->set('events.features.owner.enabled', $original);
    }
});

it('allows deleting an orphaned submission without blocking on the missing parent', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $submission = EventSubmission::factory()->create([
            'target_type' => $this->owner->getMorphClass(),
            'target_id' => $this->owner->getKey(),
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence->id,
            'event_session_id' => $this->session->id,
        ]);

        $submissionId = $submission->id;

        DB::table(config('events.database.tables.events', 'events'))
            ->where('id', $this->event->id)
            ->delete();

        $submission->delete();

        expect(EventSubmission::query()->withoutGlobalScope(EventSubmissionOwnerScope::class)->whereKey($submissionId)->exists())->toBeFalse();
    });
});

it('allows deleting an ownerless orphaned submission in explicit global context', function (): void {
    $submissionId = OwnerContext::withOwner($this->owner, function (): string {
        $submission = EventSubmission::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence->id,
            'event_session_id' => $this->session->id,
        ]);

        DB::table(config('events.database.tables.events', 'events'))
            ->where('id', $this->event->id)
            ->delete();

        return $submission->id;
    });

    OwnerContext::withOwner(null, function () use ($submissionId): void {
        $submission = EventSubmission::query()->withoutGlobalScope(EventSubmissionOwnerScope::class)->whereKey($submissionId)->firstOrFail();
        $submission->delete();

        expect(EventSubmission::query()->withoutGlobalScope(EventSubmissionOwnerScope::class)->whereKey($submissionId)->exists())->toBeFalse();
    });
});

it('rejects deleting an ownerless orphaned submission outside explicit global context', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $submission = EventSubmission::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence->id,
            'event_session_id' => $this->session->id,
        ]);

        DB::table(config('events.database.tables.events', 'events'))
            ->where('id', $this->event->id)
            ->delete();

        expect(fn () => $submission->delete())->toThrow(AuthorizationException::class, 'Explicit global');
    });
});

it('rejects deleting a foreign-target orphaned submission', function (): void {
    $submissionId = OwnerContext::withOwner($this->owner, function (): string {
        $submission = EventSubmission::factory()->create([
            'target_type' => $this->owner->getMorphClass(),
            'target_id' => $this->owner->getKey(),
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence->id,
            'event_session_id' => $this->session->id,
        ]);

        DB::table(config('events.database.tables.events', 'events'))
            ->where('id', $this->event->id)
            ->delete();

        return $submission->id;
    });

    OwnerContext::withOwner($this->other, function () use ($submissionId): void {
        $submission = EventSubmission::query()->withoutGlobalScope(EventSubmissionOwnerScope::class)->whereKey($submissionId)->firstOrFail();

        expect(fn () => $submission->delete())->toThrow(AuthorizationException::class, 'Cross-owner');
    });
});

it('cascades session deletes to linked submissions', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $submission = EventSubmission::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence->id,
            'event_session_id' => $this->session->id,
        ]);

        $this->session->delete();

        expect(EventSubmission::query()->whereKey($submission->id)->exists())->toBeFalse();
    });
});
