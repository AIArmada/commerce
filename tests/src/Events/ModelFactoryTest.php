<?php

declare(strict_types=1);

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Models\Venue;

it('creates all core models via factories', function () {
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

    $ticketType = EventTicketType::factory()->create(['event_id' => $event->id]);
    expect($ticketType->exists)->toBeTrue();

    $venue = Venue::factory()->create();
    expect($venue->exists)->toBeTrue();
});
