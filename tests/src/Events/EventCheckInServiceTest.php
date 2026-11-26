<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Events\EventAttendanceCheckedIn;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Features\Attendance\Models\EventAttendance;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
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

it('deduplicates attendee-only admissions and keeps unrelated attendees separate', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $firstAttendee = User::factory()->create();
    $secondAttendee = User::factory()->create();
    $service = app(EventCheckInService::class);

    $payload = [
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'attendee_type' => $firstAttendee->getMorphClass(),
        'attendee_id' => $firstAttendee->getKey(),
        'attendance_type' => 'walk_in',
    ];

    $first = $service->checkInWithResult($payload);
    $repeat = $service->checkInWithResult($payload);
    $other = $service->checkInWithResult([
        ...$payload,
        'attendee_type' => $secondAttendee->getMorphClass(),
        'attendee_id' => $secondAttendee->getKey(),
    ]);

    expect($first->created)->toBeTrue()
        ->and($repeat->created)->toBeFalse()
        ->and($repeat->attendance->id)->toBe($first->attendance->id)
        ->and($other->created)->toBeTrue()
        ->and($other->attendance->id)->not->toBe($first->attendance->id)
        ->and(EventAttendance::query()->count())->toBe(2);
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

it('rejects check-in for registrations outside the allowed lifecycle', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'status' => 'pending',
    ]);

    expect(fn () => app(EventCheckInService::class)->checkIn([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_registration_id' => $registration->id,
    ]))->toThrow(InvalidArgumentException::class, 'not eligible for check-in');
});

it('rejects check-in against an explicitly supplied occurrence that is not accepting', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'status' => 'cancelled',
    ]);

    expect(fn () => app(EventCheckInService::class)->checkIn([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'attendance_type' => 'walk_in',
    ]))->toThrow(InvalidArgumentException::class, 'not accepting check-ins');
});

it('rejects invalid passes during check-in', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);
    $ticketType = createEventTicketType($occurrence);
    $pass = createEventPass($ticketType, $registration, ['status' => 'revoked']);

    expect(fn () => app(EventCheckInService::class)->checkIn([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_registration_id' => $registration->id,
        'pass_id' => $pass->id,
    ]))->toThrow(InvalidArgumentException::class, 'not valid for check-in');
});

it('keeps anonymous walk-ins independent of existing attendances', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $service = app(EventCheckInService::class);

    $first = $service->checkIn([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'attendance_type' => 'walk_in',
    ]);
    $second = $service->checkIn([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'attendance_type' => 'walk_in',
    ]);

    expect($second->id)->not->toBe($first->id)
        ->and(EventAttendance::query()->count())->toBe(2);
});

it('dispatches a registration checked-in event once for a new registered attendance', function (): void {
    EventFacade::fake([RegistrationCheckedIn::class]);

    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);

    $payload = [
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_registration_id' => $registration->id,
    ];

    $service = app(EventCheckInService::class);
    $service->checkIn($payload);
    $service->checkIn($payload);

    EventFacade::assertDispatchedTimes(RegistrationCheckedIn::class, 1);
});

it('does not emit check-in events when the enclosing transaction rolls back', function (): void {
    EventFacade::fake([EventAttendanceCheckedIn::class, RegistrationCheckedIn::class]);

    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);

    expect(fn () => DB::transaction(function () use ($event, $occurrence, $registration): void {
        app(EventCheckInService::class)->checkIn([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_registration_id' => $registration->id,
        ]);

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    expect(EventAttendance::query()->where('event_registration_id', $registration->id)->exists())->toBeFalse();
    EventFacade::assertNotDispatched(EventAttendanceCheckedIn::class);
    EventFacade::assertNotDispatched(RegistrationCheckedIn::class);
});
