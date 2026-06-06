<?php

declare(strict_types=1);

use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Enums\RegistrationAttendanceSource;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Services\RegistrationService;

it('keeps registration required as the default participation mode', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Default Registration Event',
        'slug' => 'default-registration-event',
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'starts_at' => now('UTC')->addDay(),
        'timezone' => 'UTC',
    ]);

    expect($occurrence->participation_mode)->toBe(OccurrenceParticipationMode::RegistrationRequired)
        ->and($occurrence->acceptsRegistrations())->toBeTrue()
        ->and($occurrence->acceptsWalkIns())->toBeFalse();
});

it('blocks registrations and walk-ins when participation mode is none', function (): void {
    $occurrence = createParticipationModeOccurrence(OccurrenceParticipationMode::None);

    expect($occurrence->acceptsRegistrations())->toBeFalse()
        ->and($occurrence->acceptsWalkIns())->toBeFalse();

    expect(fn (): Registration => app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'No Registration Guest',
        'email' => 'no-registration@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'not accepting registrations');

    expect(fn (): Registration => app(RegistrationService::class)->recordWalkInForOccurrence($occurrence))
        ->toThrow(InvalidArgumentException::class, 'not accepting walk-ins');
});

it('records walk-in only attendance without requiring email', function (): void {
    $occurrence = createParticipationModeOccurrence(OccurrenceParticipationMode::WalkInOnly);

    expect($occurrence->acceptsRegistrations())->toBeFalse()
        ->and($occurrence->acceptsWalkIns())->toBeTrue();

    expect(fn (): Registration => app(RegistrationService::class)->createForOccurrence($occurrence, [
        'name' => 'Pre Registered Guest',
        'email' => 'pre-registered@example.com',
    ]))->toThrow(InvalidArgumentException::class, 'not accepting registrations');

    $walkIn = app(RegistrationService::class)->recordWalkInForOccurrence($occurrence);

    expect($walkIn->attendance_source)->toBe(RegistrationAttendanceSource::WalkIn)
        ->and($walkIn->status)->toBe(RegistrationStatus::CheckedIn)
        ->and($walkIn->first_name)->toBe('Walk-in')
        ->and($walkIn->last_name)->toBe('Attendee')
        ->and($walkIn->email)->toBeNull()
        ->and($walkIn->checked_in_at)->not->toBeNull();
});

it('supports hybrid occurrences with registrations and walk-ins sharing capacity', function (): void {
    $occurrence = createParticipationModeOccurrence(OccurrenceParticipationMode::Hybrid, capacity: 2);
    $service = app(RegistrationService::class);

    $registration = $service->createForOccurrence($occurrence, [
        'name' => 'Registered Guest',
        'email' => 'registered@example.com',
    ]);

    $walkIn = $service->recordWalkInForOccurrence($occurrence, [
        'name' => 'Walk In Guest',
    ]);

    expect($registration->attendance_source)->toBe(RegistrationAttendanceSource::Registration)
        ->and($registration->status)->toBe(RegistrationStatus::Pending)
        ->and($walkIn->attendance_source)->toBe(RegistrationAttendanceSource::WalkIn)
        ->and($walkIn->status)->toBe(RegistrationStatus::CheckedIn)
        ->and(Registration::query()->where('occurrence_id', $occurrence->id)->count())->toBe(2);

    expect(fn (): Registration => $service->recordWalkInForOccurrence($occurrence, [
        'name' => 'Overflow Walk In',
    ]))->toThrow(InvalidArgumentException::class, 'sold out');
});

it('syncs participation mode through the structured occurrence action', function (): void {
    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Hybrid Series',
            'slug' => 'hybrid-series',
        ],
        event: [
            'name' => 'Hybrid Event',
            'slug' => 'hybrid-event',
            'default_timezone' => 'UTC',
        ],
        venue: null,
        occurrence: [
            'starts_at' => '2026-08-21 10:00:00',
            'timezone' => 'UTC',
            'participation_mode' => OccurrenceParticipationMode::Hybrid,
        ],
    );

    expect($occurrence->participation_mode)->toBe(OccurrenceParticipationMode::Hybrid)
        ->and($occurrence->acceptsRegistrations())->toBeTrue()
        ->and($occurrence->acceptsWalkIns())->toBeTrue();
});

function createParticipationModeOccurrence(OccurrenceParticipationMode $mode, ?int $capacity = null): Occurrence
{
    $event = EventModel::query()->create([
        'name' => 'Participation ' . $mode->value,
        'slug' => 'participation-' . $mode->value . '-' . uniqid(),
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    return Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => OccurrenceStatus::Scheduled,
        'participation_mode' => $mode,
        'capacity' => $capacity,
        'starts_at' => now('UTC')->addDay(),
        'timezone' => 'UTC',
    ]);
}
