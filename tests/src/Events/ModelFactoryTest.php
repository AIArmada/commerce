<?php

declare(strict_types=1);

use AIArmada\Events\Features\Attendance\Models\EventHeadcountLog;
use AIArmada\Events\Features\Attendance\Models\EventWalkIn;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventAccessPolicy;
use AIArmada\Events\Features\Catalog\Models\EventAudienceProfile;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\Models\EventTimeExpression;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use AIArmada\Events\Features\Venues\Models\Venue;

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
    expect(createEventPass($ticketType)->exists)->toBeTrue();
    expect(EventTimeExpression::factory()->create()->exists)->toBeTrue();
    expect(EventWalkIn::factory()->create()->exists)->toBeTrue();
});

it('generates valid occurrence ranges including explicit start overrides', function (): void {
    $event = Event::factory()->create();
    $occurrences = EventOccurrence::factory()->count(10)->create(['event_id' => $event->id]);

    foreach ($occurrences as $occurrence) {
        expect($occurrence->ends_at->equalTo($occurrence->starts_at->addHours(2)))->toBeTrue();
    }

    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'starts_at' => '2030-01-01 09:00:00',
    ]);

    expect($occurrence->ends_at->equalTo($occurrence->starts_at->addHours(2)))->toBeTrue();
});
