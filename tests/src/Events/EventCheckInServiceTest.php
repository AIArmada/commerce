<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;

it('checks in an attendee', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $attendance = app(EventCheckInService::class)->checkIn([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'attendance_type' => 'walk_in',
        'check_in_source' => 'admin',
    ]);

    expect($attendance->id)->not->toBeNull();
    expect($attendance->checked_in_at)->not->toBeNull();
    expect($attendance->fresh()->logs)->toHaveCount(1);
});

it('infers the occurrence from the participant registration', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);
    $participant = $registration->participants()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'name' => 'Participant One',
        'email' => 'participant-one@example.com',
    ]);

    $attendance = app(EventCheckInService::class)->checkIn([
        'event_id' => $event->id,
        'event_registration_participant_id' => $participant->id,
        'attendance_type' => 'registered',
        'check_in_source' => 'kiosk',
    ]);

    expect($attendance->event_occurrence_id)->toBe($occurrence->id)
        ->and($attendance->event_registration_id)->toBe($registration->id)
        ->and($attendance->event_registration_participant_id)->toBe($participant->id)
        ->and($attendance->fresh()->logs)->toHaveCount(1);
});

it('reuses an active participant check-in', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);
    $participant = $registration->participants()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'name' => 'Participant One',
    ]);
    $service = app(EventCheckInService::class);
    $payload = [
        'event_id' => $event->id,
        'event_registration_participant_id' => $participant->id,
        'attendance_type' => 'registered',
        'check_in_source' => 'kiosk',
    ];

    $first = $service->checkIn($payload);
    $second = $service->checkIn($payload);

    expect($second->is($first))->toBeTrue()
        ->and($first->fresh()->logs)->toHaveCount(1);
});
