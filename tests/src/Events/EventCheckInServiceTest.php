<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Features\Attendance\Models\EventAttendance;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use Illuminate\Support\Str;

it('checks in an attendee', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $attendance = app(EventCheckInService::class)->checkIn([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'attendance_type' => 'walk_in',
        'check_in_source' => 'admin',
    ]);

    $model = EventAttendance::query()->findOrFail($attendance->id);

    expect($attendance->id)->not->toBeNull()
        ->and($attendance->checkedInAt)->not->toBeNull()
        ->and($model->logs()->count())->toBe(1);
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

    expect($attendance->occurrenceId)->toBe($occurrence->id)
        ->and($attendance->registrationId)->toBe($registration->id)
        ->and($attendance->participantId)->toBe($participant->id)
        ->and(EventAttendance::query()->findOrFail($attendance->id)->logs()->count())->toBe(1);
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

    $first = $service->checkInWithResult($payload);
    $second = $service->checkInWithResult($payload);

    expect($first->created)->toBeTrue()
        ->and($second->created)->toBeFalse()
        ->and($second->attendance->id)->toBe($first->attendance->id)
        ->and(EventAttendance::query()->findOrFail($first->attendance->id)->logs()->count())->toBe(1);
});

it('preserves the verifier and performer on a staff-assisted check-in', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $verifierId = (string) Str::uuid();

    $attendance = app(EventCheckInService::class)->checkIn([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'attendance_type' => 'registered',
        'check_in_source' => 'staff_manual',
        'verified_by_user_id' => $verifierId,
        'performed_by_type' => 'user',
        'performed_by_id' => $verifierId,
    ]);
    $log = EventAttendance::query()->findOrFail($attendance->id)->logs()->first();

    expect($attendance->verifiedByUserId)->toBe($verifierId)
        ->and($log?->performed_by_type)->toBe('user')
        ->and($log?->performed_by_id)->toBe($verifierId);
});
