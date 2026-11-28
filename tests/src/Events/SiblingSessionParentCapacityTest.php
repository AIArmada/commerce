<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Registrations\Actions\LockEventRegistrationScopeAction;
use AIArmada\Events\Features\Registrations\Actions\PromoteInterestedToConfirmedAction;
use AIArmada\Events\Features\Registrations\Actions\RegisterForFreeAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationParticipant;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('events.features.free_only.auto_derive_pricing_from_ticket_types', true);
    config()->set('events.features.owner.enabled', true);
});

it('consumes the shared occurrence aggregate across sibling sessions', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 3,
        ]);
        $sessionA = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 3,
        ]);
        $sessionB = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 3,
        ]);

        $action = app(RegisterForFreeAction::class);

        $action->execute('session', (string) $sessionA->getKey(), participants: [
            ['name' => 'Amy', 'is_primary' => true],
            ['name' => 'Ben', 'is_primary' => false],
        ]);
        $action->execute('session', (string) $sessionB->getKey(), participants: [
            ['name' => 'Cal', 'is_primary' => true],
        ]);

        expect(EventRegistration::query()->where('event_occurrence_id', $occurrence->id)->count())->toBe(3);

        expect(fn () => $action->execute('session', (string) $sessionB->getKey(), participants: [
            ['name' => 'Dee', 'is_primary' => true],
        ]))->toThrow(EventCapacityExceededException::class);

        expect(fn () => $action->execute('session', (string) $sessionA->getKey(), participants: [
            ['name' => 'Eli', 'is_primary' => true],
        ]))->toThrow(EventCapacityExceededException::class);

        expect(EventRegistration::query()->where('event_occurrence_id', $occurrence->id)->count())->toBe(3)
            ->and(EventRegistrationParticipant::query()->count())->toBe(3)
            ->and(Pass::query()->count())->toBe(3);
    });
});

it('counts direct occurrence bookings against the same parent aggregate', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 2,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 2,
        ]);

        $action = app(RegisterForFreeAction::class);

        $action->execute('occurrence', (string) $occurrence->getKey(), participants: [
            ['name' => 'Direct', 'is_primary' => true],
        ]);
        $action->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'Seated', 'is_primary' => true],
        ]);

        expect(fn () => $action->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'Overflow', 'is_primary' => true],
        ]))->toThrow(EventCapacityExceededException::class);

        expect(fn () => $action->execute('occurrence', (string) $occurrence->getKey(), participants: [
            ['name' => 'Overflow Direct', 'is_primary' => true],
        ]))->toThrow(EventCapacityExceededException::class);

        expect(EventRegistration::query()->where('event_occurrence_id', $occurrence->id)->count())->toBe(2);
    });
});

it('uses the stricter session cap when the session is tighter than the parent', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 5,
        ]);
        $tightSession = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 1,
        ]);
        $roomySession = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 5,
        ]);

        $action = app(RegisterForFreeAction::class);

        $action->execute('session', (string) $tightSession->getKey(), participants: [
            ['name' => 'First', 'is_primary' => true],
        ]);

        expect(fn () => $action->execute('session', (string) $tightSession->getKey(), participants: [
            ['name' => 'Second', 'is_primary' => true],
        ]))->toThrow(EventCapacityExceededException::class);

        $sibling = $action->execute('session', (string) $roomySession->getKey(), participants: [
            ['name' => 'Sibling', 'is_primary' => true],
        ]);

        expect($sibling)->toHaveCount(1);
    });
});

it('treats null session capacity as parent-bounded', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 1,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => null,
        ]);

        $action = app(RegisterForFreeAction::class);

        $action->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'First', 'is_primary' => true],
        ]);

        expect(fn () => $action->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'Second', 'is_primary' => true],
        ]))->toThrow(EventCapacityExceededException::class);
    });
});

it('treats null parent capacity as session-bounded', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => null,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 1,
        ]);

        $action = app(RegisterForFreeAction::class);

        $action->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'First', 'is_primary' => true],
        ]);

        expect(fn () => $action->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'Second', 'is_primary' => true],
        ]))->toThrow(EventCapacityExceededException::class);
    });
});

it('leaves no partial records when a batch exceeds capacity', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 2,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 2,
        ]);

        expect(fn () => app(RegisterForFreeAction::class)->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'One', 'is_primary' => true],
            ['name' => 'Two', 'is_primary' => false],
            ['name' => 'Three', 'is_primary' => false],
        ]))->toThrow(EventCapacityExceededException::class);

        expect(EventRegistration::query()->count())->toBe(0)
            ->and(EventRegistrationParticipant::query()->count())->toBe(0)
            ->and(Pass::query()->count())->toBe(0);
    });
});

it('does not count cancelled or waitlisted registrations against capacity', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 2,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 2,
        ]);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'cancelled',
            'total_participants' => 1,
        ]);
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'waitlisted',
            'total_participants' => 1,
        ]);

        $registrations = app(RegisterForFreeAction::class)->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'First', 'is_primary' => true],
            ['name' => 'Second', 'is_primary' => false],
        ]);

        expect($registrations)->toHaveCount(2);

        expect(fn () => app(RegisterForFreeAction::class)->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'Third', 'is_primary' => true],
        ]))->toThrow(EventCapacityExceededException::class);
    });
});

it('blocks promotion when the parent occurrence is exhausted despite session room', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 1,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 3,
        ]);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);
        $interested = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'interested',
        ]);

        expect(fn () => app(PromoteInterestedToConfirmedAction::class)->execute((string) $interested->getKey()))
            ->toThrow(EventCapacityExceededException::class, 'Occurrence');

        expect($interested->fresh()?->status->getValue())->toBe('interested')
            ->and(Pass::query()->count())->toBe(0);
    });
});

it('rejects cross-owner session registration without writing', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    [$event, $session] = OwnerContext::withOwner($ownerA, function (): array {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 5,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 5,
        ]);
        $session->load(['event', 'occurrence']);

        return [$event, $session];
    });

    expect(fn () => OwnerContext::withOwner($ownerB, function () use ($session): void {
        app(RegisterForFreeAction::class)->execute('session', (string) $session->getKey(), participants: [
            ['name' => 'Intruder', 'is_primary' => true],
        ]);
    }))->toThrow(AuthorizationException::class);

    expect(fn () => OwnerContext::withOwner($ownerB, function () use ($session): void {
        $scope = app(EventRegistrationScopeResolver::class)->resolve('session', (string) $session->getKey());

        DB::transaction(fn () => app(LockEventRegistrationScopeAction::class)->handle($scope));
    }))->toThrow(AuthorizationException::class);

    $count = OwnerContext::withOwner($ownerA, function () use ($event): int {
        return EventRegistration::query()->where('event_id', $event->id)->count();
    });

    expect($count)->toBe(0);
});

it('locks event then occurrence then session in one order', function (): void {
    // SQLite ignores FOR UPDATE, so this asserts deterministic parent-first
    // lock order rather than row-level serialization.
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 3,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 3,
        ]);

        $scope = app(EventRegistrationScopeResolver::class)->resolve('session', (string) $session->getKey());

        $lockedTables = [];

        DB::listen(function ($query) use (&$lockedTables): void {
            if (preg_match('/from\s+"([^"]+)"/', $query->sql, $matches) === 1) {
                $lockedTables[] = $matches[1];
            }
        });

        DB::transaction(fn () => app(LockEventRegistrationScopeAction::class)->handle($scope));

        expect($lockedTables)->toBe([
            config('events.database.tables.events', 'events'),
            config('events.database.tables.event_occurrences', 'event_occurrences'),
            config('events.database.tables.event_sessions', 'event_sessions'),
        ]);
    });
});
