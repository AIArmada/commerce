<?php

declare(strict_types=1);

use AIArmada\Events\Data\EventData;
use AIArmada\Events\Data\EventDetailData;
use AIArmada\Events\Data\EventOccurrenceData;
use AIArmada\Events\Data\EventSessionData;
use AIArmada\Events\Data\RegistrationData;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;

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
