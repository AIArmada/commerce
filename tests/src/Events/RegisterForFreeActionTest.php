<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\RegisterForFreeAction;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\NotFreeEventException;
use AIArmada\Events\Exceptions\OpenDoorRegistrationBlockedException;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;

beforeEach(function (): void {
    config()->set('events.features.free_only.auto_derive_pricing_from_ticket_types', true);
    config()->set('events.features.owner.enabled', true);
});

it('registers a single participant for a free event', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $registrations = app(RegisterForFreeAction::class)->execute(
            target: $occurrence,
            participants: [
                ['name' => 'Alice', 'email' => 'alice@example.com', 'is_primary' => true],
            ],
        );

        expect($registrations)->toHaveCount(1);
        $registration = $registrations->first();
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
            target: $occurrence,
            participants: [
                ['name' => 'Alice', 'email' => 'alice@example.com', 'is_primary' => true],
                ['name' => 'Bob', 'email' => 'bob@example.com'],
            ],
        );

        expect($registrations)->toHaveCount(2);
    });
});

it('throws for paid events', function (): void {
    expect(fn () => OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->paid()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        app(RegisterForFreeAction::class)->execute(
            target: $occurrence,
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
            target: $occurrence,
            participants: [['name' => 'Alice', 'is_primary' => true]],
        );
    }))->toThrow(OpenDoorRegistrationBlockedException::class);
});

it('creates interested registrations when passes are not issued', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->freeWithOptionalRegistration()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $registrations = app(RegisterForFreeAction::class)->execute(
            target: $occurrence,
            participants: [['name' => 'Alice', 'is_primary' => true]],
            options: ['with_pass' => false],
        );

        expect($registrations->first()->status->getValue())->toBe('interested');
    });
});

it('creates confirmed registrations with passes when requested', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $registrations = app(RegisterForFreeAction::class)->execute(
            target: $occurrence,
            participants: [['name' => 'Alice', 'is_primary' => true]],
        );

        $registration = $registrations->first();
        expect($registration->status->getValue())->toBe('confirmed');
        expect($registration->passes)->toHaveCount(1);
    });
});

it('accepts event-level target', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();

        $registrations = app(RegisterForFreeAction::class)->execute(
            target: $event,
            participants: [['name' => 'Alice', 'is_primary' => true]],
        );

        expect($registrations)->toHaveCount(1);
        expect($registrations->first()->event_id)->toBe($event->id);
    });
});

it('uses ticket-type derived free pricing', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->published()->create();
        createEventTicketType($event, ['price' => 0]);
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $registrations = app(RegisterForFreeAction::class)->execute(
            target: $occurrence,
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
            target: $session,
            participants: [
                ['name' => 'Alice', 'is_primary' => true],
                ['name' => 'Bob', 'is_primary' => false],
            ],
        );
    }))->toThrow(EventCapacityExceededException::class);
});
