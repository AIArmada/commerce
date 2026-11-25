<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\EventRegistrationNotAvailableException;
use AIArmada\Events\Exceptions\NotFreeEventException;
use AIArmada\Events\Exceptions\OpenDoorRegistrationBlockedException;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Cancelled;
use AIArmada\Events\Features\Registrations\Actions\RegisterForFreeAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;

beforeEach(function (): void {
    config()->set('events.features.free_only.auto_derive_pricing_from_ticket_types', true);
    config()->set('events.features.owner.enabled', true);
});

it('registers a single participant for a free event', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $registrations = app(RegisterForFreeAction::class)->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: [
                ['name' => 'Alice', 'email' => 'alice@example.com', 'is_primary' => true],
            ],
        );

        expect($registrations)->toHaveCount(1);
        $registration = EventRegistration::query()->findOrFail($registrations[0]);
        expect($registration->exists)->toBeTrue();
        expect($registration->status->getValue())->toBe('confirmed');
        expect($registration->event_id)->toBe($event->id);
        expect($registration->event_occurrence_id)->toBe($occurrence->id);
    });
});

it('registers multiple participants', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $registrations = app(RegisterForFreeAction::class)->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: [
                ['name' => 'Alice', 'email' => 'alice@example.com', 'is_primary' => true],
                ['name' => 'Bob', 'email' => 'bob@example.com'],
            ],
        );

        expect($registrations)->toHaveCount(2);
    });
});

it('returns the same registration batch for an idempotent retry', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $participants = [
            ['name' => 'Alice', 'email' => 'alice@example.com', 'is_primary' => true],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $action = app(RegisterForFreeAction::class);
        $first = $action->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: $participants,
            options: ['idempotency_key' => 'checkout-attempt-1'],
        );
        $retry = $action->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: $participants,
            options: ['idempotency_key' => 'checkout-attempt-1'],
        );

        expect($first)->toHaveCount(2)
            ->and($retry)->toHaveCount(2)
            ->and($retry)->toBe($first)
            ->and(EventRegistration::query()->where('event_occurrence_id', $occurrence->id)->count())->toBe(2);
    });
});

it('throws for paid events', function (): void {
    expect(fn () => OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->paid()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        app(RegisterForFreeAction::class)->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: [['name' => 'Alice', 'is_primary' => true]],
        );
    }))->toThrow(NotFreeEventException::class);
});

it('throws for open-door events with block mode', function (): void {
    config()->set('events.features.free_only.open_door_mode', 'block');
    expect(fn () => OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->freeOpenDoor()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        app(RegisterForFreeAction::class)->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: [['name' => 'Alice', 'is_primary' => true]],
        );
    }))->toThrow(OpenDoorRegistrationBlockedException::class);
});

it('rejects registration when the occurrence lifecycle is closed', function (): void {
    expect(fn () => OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $occurrence->status->transitionTo(Cancelled::class);

        app(RegisterForFreeAction::class)->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: [['name' => 'Alice', 'is_primary' => true]],
        );
    }))->toThrow(EventRegistrationNotAvailableException::class);
});

it('creates interested registrations when passes are not issued', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->freeWithOptionalRegistration()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $registrations = app(RegisterForFreeAction::class)->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: [['name' => 'Alice', 'is_primary' => true]],
            options: ['with_pass' => false],
        );

        expect(EventRegistration::query()->findOrFail($registrations[0])->status->getValue())->toBe('interested');
    });
});

it('creates confirmed registrations with passes when requested', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $registrations = app(RegisterForFreeAction::class)->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: [['name' => 'Alice', 'is_primary' => true]],
        );

        $registration = EventRegistration::query()->findOrFail($registrations[0]);
        expect($registration->status->getValue())->toBe('confirmed');
        expect($registration->passes)->toHaveCount(1);
    });
});

it('accepts event-level target', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();

        $registrations = app(RegisterForFreeAction::class)->execute(
            'event',
            (string) $event->getKey(),
            participants: [['name' => 'Alice', 'is_primary' => true]],
        );

        expect($registrations)->toHaveCount(1);
        expect(EventRegistration::query()->findOrFail($registrations[0])->event_id)->toBe($event->id);
    });
});

it('uses ticket-type derived free pricing', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->published()->create();
        createEventTicketType($event, ['price' => 0]);
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $registrations = app(RegisterForFreeAction::class)->execute(
            'occurrence',
            (string) $occurrence->getKey(),
            participants: [['name' => 'Alice', 'is_primary' => true]],
        );

        expect($registrations)->toHaveCount(1);
    });
});

it('falls back to occurrence capacity for session registrations', function (): void {
    expect(fn () => OwnerContext::withOwner(null, function (): void {
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

        app(RegisterForFreeAction::class)->execute(
            'session',
            (string) $session->getKey(),
            participants: [
                ['name' => 'Alice', 'is_primary' => true],
                ['name' => 'Bob', 'is_primary' => false],
            ],
        );
    }))->toThrow(EventCapacityExceededException::class);
});
