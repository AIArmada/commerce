<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\SyncEventClassificationsAction;
use AIArmada\Events\Actions\SyncEventInvolvementsAction;
use AIArmada\Events\Actions\SyncEventLanguagesAction;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventInvolvement;
use AIArmada\Events\Models\EventLanguage;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRole;
use AIArmada\Events\Models\EventSession;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', true);

    $this->owner = User::factory()->create();

    OwnerContext::withOwner($this->owner, function (): void {
        EventRole::factory()->create(['code' => 'organizer', 'name' => 'Organizer']);
        EventRole::factory()->create(['code' => 'speaker', 'name' => 'Speaker']);
        EventRole::factory()->create(['code' => 'moderator', 'name' => 'Moderator']);

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

function seedScopeInvolvement(Event $event, ?string $occurrenceId, ?string $sessionId, string $role, ?string $name = null): EventInvolvement
{
    return EventInvolvement::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrenceId,
        'event_session_id' => $sessionId,
        'role_code' => $role,
        'display_name' => $name ?? ucfirst($role) . ' Name',
    ]);
}

// Owner isolation: a child scope owned by another owner cannot be synced.
it('rejects involvement syncs for session scopes outside the owner boundary', function (): void {
    $other = User::factory()->create();

    OwnerContext::withOwner($this->owner, function () use ($other): void {
        $foreignEvent = Event::factory()->create();
        $foreignOccurrence = EventOccurrence::factory()->create(['event_id' => $foreignEvent->id]);
        $foreignSession = EventSession::factory()->create([
            'event_id' => $foreignEvent->id,
            'event_occurrence_id' => $foreignOccurrence->id,
        ]);

        OwnerContext::withOwner($other, function () use ($foreignSession): void {
            app(SyncEventInvolvementsAction::class)->handle($foreignSession, [
                ['role_code' => 'speaker', 'display_name' => 'Intruder'],
            ], ['speaker']);
        });
    });
})->throws(AuthorizationException::class);

it('rejects language syncs for occurrence scopes outside the owner boundary', function (): void {
    $other = User::factory()->create();

    OwnerContext::withOwner($other, function (): void {
        app(SyncEventLanguagesAction::class)->handle($this->occurrence, ['ms']);
    });
})->throws(AuthorizationException::class);

it('rejects classification syncs for session scopes outside the owner boundary', function (): void {
    $other = User::factory()->create();

    OwnerContext::withOwner($other, function (): void {
        app(SyncEventClassificationsAction::class)->handle($this->session, ['topic' => ['Music']]);
    });
})->throws(AuthorizationException::class);

// Forged persisted child: dirty in-memory attributes must not redirect writes.
it('writes involvement rows to the stored event when the session model is forged in memory', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $otherEvent = Event::factory()->create();

        $forged = clone $this->session;
        $forged->event_id = $otherEvent->id;

        app(SyncEventInvolvementsAction::class)->handle($forged, [
            ['role_code' => 'speaker', 'display_name' => 'Stored Scope Speaker'],
        ], ['speaker']);

        expect(EventInvolvement::query()->where('event_id', $this->event->id)->count())->toBe(1)
            ->and(EventInvolvement::query()->where('event_id', $otherEvent->id)->count())->toBe(0)
            ->and(EventInvolvement::query()->firstOrFail()->event_session_id)->toBe($this->session->id);
    });
});

it('writes language rows to the stored event when the occurrence model is forged in memory', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $otherEvent = Event::factory()->create();

        $forged = clone $this->occurrence;
        $forged->event_id = $otherEvent->id;

        app(SyncEventLanguagesAction::class)->handle($forged, ['ms']);

        expect(EventLanguage::query()->where('event_id', $this->event->id)->count())->toBe(1)
            ->and(EventLanguage::query()->where('event_id', $otherEvent->id)->count())->toBe(0);
    });
});

it('writes classification rows to the stored event when the session model is forged in memory', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $otherEvent = Event::factory()->create();

        $forged = clone $this->session;
        $forged->event_id = $otherEvent->id;

        app(SyncEventClassificationsAction::class)->handle($forged, ['topic' => ['Music']]);

        expect(EventClassification::query()->where('event_id', $this->event->id)->count())->toBe(1)
            ->and(EventClassification::query()->where('event_id', $otherEvent->id)->count())->toBe(0);
    });
});

// Null occurrence: each canonical scope only replaces its own rows.
it('keeps occurrence and session involvement rows when syncing the null-occurrence event scope', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        seedScopeInvolvement($this->event, null, null, 'speaker', 'Event Speaker');
        $occurrenceRow = seedScopeInvolvement($this->event, $this->occurrence->id, null, 'speaker', 'Occurrence Speaker');
        $sessionRow = seedScopeInvolvement($this->event, $this->occurrence->id, $this->session->id, 'speaker', 'Session Speaker');

        app(SyncEventInvolvementsAction::class)->handle($this->event, [
            ['role_code' => 'speaker', 'display_name' => 'Replacement Speaker'],
        ], ['speaker']);

        expect(EventInvolvement::query()->whereNull('event_occurrence_id')->whereNull('event_session_id')->count())->toBe(1)
            ->and(EventInvolvement::query()->whereNull('event_occurrence_id')->firstOrFail()->display_name)->toBe('Replacement Speaker')
            ->and(EventInvolvement::query()->whereKey($occurrenceRow->id)->exists())->toBeTrue()
            ->and(EventInvolvement::query()->whereKey($sessionRow->id)->exists())->toBeTrue();
    });
});

it('keeps event and session involvement rows when syncing the occurrence scope', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $eventRow = seedScopeInvolvement($this->event, null, null, 'speaker', 'Event Speaker');
        seedScopeInvolvement($this->event, $this->occurrence->id, null, 'speaker', 'Occurrence Speaker');
        $sessionRow = seedScopeInvolvement($this->event, $this->occurrence->id, $this->session->id, 'speaker', 'Session Speaker');

        $synced = app(SyncEventInvolvementsAction::class)->handle($this->occurrence, [
            ['role_code' => 'speaker', 'display_name' => 'Replacement Speaker'],
        ], ['speaker']);

        expect($synced)->toBe(1)
            ->and(EventInvolvement::query()->where('event_occurrence_id', $this->occurrence->id)->whereNull('event_session_id')->count())->toBe(1)
            ->and(EventInvolvement::query()->whereKey($eventRow->id)->exists())->toBeTrue()
            ->and(EventInvolvement::query()->whereKey($sessionRow->id)->exists())->toBeTrue();
    });
});

it('keeps event and occurrence language rows when syncing the session scope', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        EventLanguage::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => null,
            'event_session_id' => null,
            'language_code' => 'en',
        ]);
        EventLanguage::factory()->create([
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence->id,
            'event_session_id' => null,
            'language_code' => 'ar',
        ]);

        app(SyncEventLanguagesAction::class)->handle($this->session, ['ms']);

        expect(EventLanguage::query()->whereNull('event_session_id')->count())->toBe(2)
            ->and(EventLanguage::query()->where('event_session_id', $this->session->id)->count())->toBe(1)
            ->and(EventLanguage::query()->where('event_session_id', $this->session->id)->firstOrFail()->language_code)->toBe('ms');
    });
});

it('keeps event classification rows when syncing the session scope', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $action = app(SyncEventClassificationsAction::class);
        $action->handle($this->event, ['topic' => ['Music']]);

        $action->handle($this->session, ['topic' => ['Arts']]);

        expect(EventClassification::query()->whereNull('event_session_id')->count())->toBe(1)
            ->and(EventClassification::query()->where('event_session_id', $this->session->id)->count())->toBe(1);
    });
});

// Managed roles: empty filters reject, rows must stay inside the filter.
it('rejects an explicit empty managed role filter without deleting anything', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        seedScopeInvolvement($this->event, null, null, 'speaker');

        try {
            app(SyncEventInvolvementsAction::class)->handle($this->event, [], []);
            expect(false)->toBeTrue('Expected an InvalidArgumentException for an empty role filter.');
        } catch (InvalidArgumentException $exception) {
            expect($exception->getMessage())->toContain('must not be empty');
        }

        expect(EventInvolvement::query()->count())->toBe(1);
    });
});

it('rejects involvement rows outside the explicit managed roles', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $existing = seedScopeInvolvement($this->event, null, null, 'speaker');

        try {
            app(SyncEventInvolvementsAction::class)->handle($this->event, [
                ['role_code' => 'moderator', 'display_name' => 'Out Of Filter'],
            ], ['speaker']);
            expect(false)->toBeTrue('Expected an InvalidArgumentException for rows outside the filter.');
        } catch (InvalidArgumentException $exception) {
            expect($exception->getMessage())->toContain('outside the managed roles');
        }

        expect(EventInvolvement::query()->whereKey($existing->id)->exists())->toBeTrue()
            ->and(EventInvolvement::query()->count())->toBe(1);
    });
});

it('rejects unknown and inactive role codes without deleting anything', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        EventRole::factory()->create(['code' => 'retired', 'name' => 'Retired', 'is_active' => false]);
        seedScopeInvolvement($this->event, null, null, 'speaker');

        expect(fn () => app(SyncEventInvolvementsAction::class)->handle($this->event, [
            ['role_code' => 'ghost', 'display_name' => 'Ghost'],
        ]))->toThrow(InvalidArgumentException::class, 'Unknown involvement role codes');

        expect(fn () => app(SyncEventInvolvementsAction::class)->handle($this->event, [
            ['role_code' => 'retired', 'display_name' => 'Retired Row'],
        ]))->toThrow(InvalidArgumentException::class, 'Inactive involvement role codes');

        expect(EventInvolvement::query()->count())->toBe(1);
    });
});

// Organizer preservation requires an explicit opt-in.
it('preserves organizer rows by default and rejects organizer input without the opt-in', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $organizer = seedScopeInvolvement($this->event, null, null, 'organizer', 'Kept Organizer');
        seedScopeInvolvement($this->event, null, null, 'speaker', 'Old Speaker');

        app(SyncEventInvolvementsAction::class)->handle($this->event, [
            ['role_code' => 'speaker', 'display_name' => 'New Speaker'],
        ]);

        expect(EventInvolvement::query()->whereKey($organizer->id)->exists())->toBeTrue()
            ->and(EventInvolvement::query()->role('speaker')->count())->toBe(1);

        expect(fn () => app(SyncEventInvolvementsAction::class)->handle($this->event, [
            ['role_code' => 'organizer', 'display_name' => 'Sneaky Organizer'],
        ]))->toThrow(InvalidArgumentException::class, 'organizer');

        expect(EventInvolvement::query()->role('organizer')->count())->toBe(1);
    });
});

it('replaces organizer rows when the organizer opt-in is explicit', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $organizer = seedScopeInvolvement($this->event, null, null, 'organizer', 'Old Organizer');

        app(SyncEventInvolvementsAction::class)->handle($this->event, [
            ['role_code' => 'organizer', 'display_name' => 'New Organizer'],
        ], ['organizer']);

        expect(EventInvolvement::query()->whereKey($organizer->id)->exists())->toBeFalse()
            ->and(EventInvolvement::query()->role('organizer')->firstOrFail()->display_name)->toBe('New Organizer');
    });
});

// Duplicate identities collapse per scope and role; visibility is preserved as given.
it('deduplicates repeated identities per scope and role', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $personId = (string) Str::uuid();

        $synced = app(SyncEventInvolvementsAction::class)->handle($this->session, [
            ['role_code' => 'speaker', 'involveable_type' => 'person', 'involveable_id' => $personId],
            ['role_code' => 'speaker', 'involveable_type' => 'person', 'involveable_id' => $personId],
            ['role_code' => 'moderator', 'involveable_type' => 'person', 'involveable_id' => $personId],
        ], ['speaker', 'moderator']);

        expect($synced)->toBe(2)
            ->and(EventInvolvement::query()->where('event_session_id', $this->session->id)->count())->toBe(2)
            ->and(EventInvolvement::query()->where('event_session_id', $this->session->id)->role('speaker')->count())->toBe(1);
    });
});

it('stores the given visibility on synced rows', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        app(SyncEventInvolvementsAction::class)->handle($this->event, [
            ['role_code' => 'speaker', 'display_name' => 'Private Speaker', 'visibility' => 'private'],
            ['role_code' => 'moderator', 'display_name' => 'Public Moderator', 'visibility' => 'public'],
        ], ['speaker', 'moderator']);

        expect(EventInvolvement::query()->role('speaker')->firstOrFail()->visibility)->toBe('private')
            ->and(EventInvolvement::query()->role('moderator')->firstOrFail()->visibility)->toBe('public');
    });
});
