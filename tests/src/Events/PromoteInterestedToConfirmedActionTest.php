<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\NotInterestedRegistrationException;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Registrations\Actions\PromoteInterestedToConfirmedAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Ticketing\Features\Passes\Models\Pass;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', true);
});

it('promotes interested registration to confirmed', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'interested',
        ]);

        app(PromoteInterestedToConfirmedAction::class)->execute((string) $registration->getKey());

        $registration->refresh();

        expect($registration->status->getValue())->toBe('confirmed');
        expect($registration->approved_at)->not->toBeNull();
    });
});

it('throws for non-interested registrations', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
        ]);

        app(PromoteInterestedToConfirmedAction::class)->execute((string) $registration->getKey());
    });
})->throws(NotInterestedRegistrationException::class);

it('issues passes on promotion', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'interested',
        ]);

        app(PromoteInterestedToConfirmedAction::class)->execute((string) $registration->getKey());

        expect($registration->fresh()->passes)->toHaveCount(1);
    });
});

it('blocks promotion when the session is at capacity', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'capacity' => 1,
        ]);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);

        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'interested',
        ]);

        app(PromoteInterestedToConfirmedAction::class)->execute((string) $registration->getKey());
    });
})->throws(EventCapacityExceededException::class);

it('falls back to occurrence capacity when the session has no override', function (): void {
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

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);

        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'interested',
        ]);

        app(PromoteInterestedToConfirmedAction::class)->execute((string) $registration->getKey());
    }))->toThrow(EventCapacityExceededException::class, 'Occurrence');
});

it('does not issue passes when the session overrides free pass issuance off', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create([
            'issue_passes_for_free' => true,
        ]);
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'issue_passes_for_free' => true,
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'issue_passes_for_free' => false,
        ]);

        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'status' => 'interested',
        ]);

        app(PromoteInterestedToConfirmedAction::class)->execute((string) $registration->getKey());

        expect($registration->fresh()->passes)->toHaveCount(0);
    });
});

it('blocks promotion when a group interested registration exceeds remaining capacity', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 3,
        ]);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'confirmed',
            'total_participants' => 2,
        ]);

        // Group interest is constructible through the canonical service
        // contract, which accepts any positive total_participants.
        $group = EventRegistration::query()->findOrFail(app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'registration_type' => 'group',
            'status' => 'interested',
            'source' => 'group_rsvp',
            'total_participants' => 3,
        ]));

        expect(fn () => app(PromoteInterestedToConfirmedAction::class)->execute((string) $group->getKey()))
            ->toThrow(EventCapacityExceededException::class);

        expect($group->fresh()?->status->getValue())->toBe('interested')
            ->and(Pass::query()->count())->toBe(0);
    });
});

it('promotes a group interested registration when remaining capacity fits', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 3,
        ]);

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);

        $group = EventRegistration::query()->findOrFail(app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'registration_type' => 'group',
            'status' => 'interested',
            'source' => 'group_rsvp',
            'total_participants' => 2,
        ]));

        app(PromoteInterestedToConfirmedAction::class)->execute((string) $group->getKey());

        expect($group->fresh()->status->getValue())->toBe('confirmed')
            ->and($occurrence->fresh()?->capacityRemaining())->toBe(0);
    });
});
