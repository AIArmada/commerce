<?php

declare(strict_types=1);

use AIArmada\Events\Data\EventData;
use AIArmada\Events\Data\EventDetailData;
use AIArmada\Events\Data\EventLocationData;
use AIArmada\Events\Data\EventOccurrenceData;
use AIArmada\Events\Data\EventSessionData;
use AIArmada\Events\Data\RegistrationData;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventLocation;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Models\VenueSpace;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('serializes state-backed statuses as strings in data transfer objects', function (): void {
    $event = Event::factory()->published()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'status' => 'rescheduled',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'status' => 'cancelled',
    ]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'status' => 'interested',
    ]);

    $eventData = EventData::fromEvent($event);
    $detailData = EventDetailData::fromEvent($event);
    $occurrenceData = EventOccurrenceData::fromOccurrence($occurrence);
    $sessionData = EventSessionData::fromEventSession($session);
    $registrationData = RegistrationData::fromRegistration($registration);

    expect($eventData->status)->toBe('published')
        ->and($detailData->status)->toBe('published')
        ->and($occurrenceData->status)->toBe('rescheduled')
        ->and($sessionData->status)->toBe('cancelled')
        ->and($registrationData->status)->toBe('interested');
});

it('projects only the event-level primary location into event data', function (): void {
    $event = Event::factory()->published()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    EventLocation::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'city' => 'Nested City',
        'state' => 'Nested State',
        'country_code' => 'MY',
    ]);
    EventLocation::factory()->create([
        'event_id' => $event->id,
        'city' => 'Event City',
        'state' => 'Event State',
        'country_code' => 'MY',
    ]);

    expect(EventData::fromEvent($event)->location_summary)->toBe('Event City, Event State, MY');
});

it('snapshots venue space names and exposes them in location data', function (): void {
    $event = Event::factory()->create();
    $space = VenueSpace::factory()->create(['name' => 'Room A']);

    $location = EventLocation::factory()->create([
        'event_id' => $event->id,
        'venue_space_id' => $space->id,
    ]);

    $space->update(['name' => 'Room B']);
    $location->refresh();

    expect($location->space_name_snapshot)->toBe('Room A')
        ->and(EventLocationData::fromEventLocation($location)->space_name_snapshot)->toBe('Room A');
});

it('enforces venue space slug uniqueness by ownership scope', function (): void {
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();

    VenueSpace::factory()->create(['slug' => 'auditorium']);
    VenueSpace::factory()->create(['slug' => 'auditorium', 'venue_id' => $venueA->id]);
    VenueSpace::factory()->create(['slug' => 'auditorium', 'venue_id' => $venueB->id]);

    expect(fn (): VenueSpace => VenueSpace::factory()->create(['slug' => 'auditorium']))
        ->toThrow(QueryException::class);

    expect(fn (): VenueSpace => VenueSpace::factory()->create([
        'slug' => 'auditorium',
        'venue_id' => $venueA->id,
    ]))->toThrow(QueryException::class);
});
