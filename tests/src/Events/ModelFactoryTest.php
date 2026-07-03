<?php

declare(strict_types=1);

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAccessPolicy;
use AIArmada\Events\Models\EventAudienceProfile;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventHeadcountLog;
use AIArmada\Events\Models\EventNotificationBatch;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTimeExpression;
use AIArmada\Events\Models\EventWalkIn;
use AIArmada\Events\Models\Venue;

it('creates all core models via factories', function (): void {
    $event = Event::factory()->create();
    expect($event->exists)->toBeTrue();

    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    expect($occurrence->exists)->toBeTrue();

    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);
    expect($session->exists)->toBeTrue();

    $registration = EventRegistration::factory()->create(['event_id' => $event->id]);
    expect($registration->exists)->toBeTrue();

    $ticketType = createEventTicketType($event);
    expect($ticketType->exists)->toBeTrue();

    $venue = Venue::factory()->create();
    expect($venue->exists)->toBeTrue();
});

it('creates related event models via their default factories', function (): void {
    expect(EventOccurrence::factory()->create()->exists)->toBeTrue();
    expect(EventSession::factory()->create()->exists)->toBeTrue();
    expect(EventRegistration::factory()->create()->exists)->toBeTrue();
    $ticketType = createEventTicketType(Event::factory()->create());
    expect($ticketType->exists)->toBeTrue();
    expect(EventAccessPolicy::factory()->create()->exists)->toBeTrue();
    expect(EventAudienceProfile::factory()->create()->exists)->toBeTrue();
    expect(EventClassification::factory()->create()->exists)->toBeTrue();
    expect(EventHeadcountLog::factory()->create()->exists)->toBeTrue();
    expect(EventNotificationBatch::factory()->create()->exists)->toBeTrue();
    expect(createEventPass($ticketType)->exists)->toBeTrue();
    expect(EventTimeExpression::factory()->create()->exists)->toBeTrue();
    expect(EventWalkIn::factory()->create()->exists)->toBeTrue();
});
