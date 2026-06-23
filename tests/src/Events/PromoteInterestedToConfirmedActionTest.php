<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\PromoteInterestedToConfirmedAction;
use AIArmada\Events\Contracts\EventPassIssuer;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\NotInterestedRegistrationException;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;

use function Pest\Laravel\mock;

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

        $result = app(PromoteInterestedToConfirmedAction::class)->execute($registration);

        expect($result->status->getValue())->toBe('confirmed');
        expect($result->approved_at)->not->toBeNull();
    });
});

it('throws for non-interested registrations', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
        ]);

        app(PromoteInterestedToConfirmedAction::class)->execute($registration);
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

        $result = app(PromoteInterestedToConfirmedAction::class)->execute($registration);

        expect($result->passes)->toHaveCount(1);
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

        app(PromoteInterestedToConfirmedAction::class)->execute($registration);
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

        app(PromoteInterestedToConfirmedAction::class)->execute($registration);
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

        mock(EventPassIssuer::class)
            ->shouldNotReceive('issuePassesFor');

        $result = app(PromoteInterestedToConfirmedAction::class)->execute($registration);

        expect($result->passes)->toHaveCount(0);
    });
});
