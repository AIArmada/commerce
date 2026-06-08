<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Services\RegistrationService;
use Illuminate\Support\Facades\Schema;

it('persists the registration_required column on the events table', function (): void {
    expect(Schema::hasColumn('events', 'registration_required'))->toBeTrue();
});

it('accepts a registration_required flag via EnsureOccurrenceAction', function (): void {
    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Registration Required Series',
            'slug' => 'registration-required-series',
        ],
        event: [
            'name' => 'Registration Required Event',
            'slug' => 'registration-required-event-' . uniqid(),
            'registration_required' => true,
        ],
        occurrence: [
            'starts_at' => now('UTC')->addDay(),
            'timezone' => 'UTC',
            'status' => OccurrenceStatus::Scheduled,
        ],
    );

    $event = OwnerContext::withOwner(
        null,
        static fn (): EventModel => EventModel::query()->findOrFail($occurrence->event_id),
    );

    expect((bool) $event->registration_required)->toBeTrue();
});

it('refuses registration when registration_required is false', function (): void {
    $occurrence = OwnerContext::withOwner(null, static function (): Occurrence {
        return app(EnsureOccurrenceAction::class)->handle(
            series: [
                'name' => 'No Registration Series',
                'slug' => 'no-registration-series',
            ],
            event: [
                'name' => 'No Registration Event',
                'slug' => 'no-registration-event-' . uniqid(),
                'registration_required' => false,
            ],
            occurrence: [
                'starts_at' => now('UTC')->addDay(),
                'timezone' => 'UTC',
                'status' => OccurrenceStatus::Scheduled,
                'participation_mode' => OccurrenceParticipationMode::RegistrationRequired,
            ],
        );
    });

    expect(fn () => OwnerContext::withOwner(null, static function () use ($occurrence): void {
        app(RegistrationService::class)->createForOccurrence($occurrence, [
            'name' => 'Attempted Registrant',
            'email' => 'attempted-' . uniqid() . '@example.com',
        ]);
    }))->toThrow(InvalidArgumentException::class, 'not required');
});

it('proceeds with registration when registration_required is true', function (): void {
    $occurrence = OwnerContext::withOwner(null, static function (): Occurrence {
        return app(EnsureOccurrenceAction::class)->handle(
            series: [
                'name' => 'Allow Registration Series',
                'slug' => 'allow-registration-series',
            ],
            event: [
                'name' => 'Allow Registration Event',
                'slug' => 'allow-registration-event-' . uniqid(),
                'registration_required' => true,
            ],
            occurrence: [
                'starts_at' => now('UTC')->addDay(),
                'timezone' => 'UTC',
                'status' => OccurrenceStatus::Scheduled,
                'participation_mode' => OccurrenceParticipationMode::RegistrationRequired,
            ],
        );
    });

    $registration = OwnerContext::withOwner(null, static function () use ($occurrence) {
        return app(RegistrationService::class)->createForOccurrence($occurrence, [
            'name' => 'Allowed Registrant',
            'email' => 'allowed-' . uniqid() . '@example.com',
        ]);
    });

    expect($registration->id)->not->toBeNull();
});

it('defaults registration_required to false on direct model creation', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Default Registration Event',
        'slug' => 'default-registration-event-' . uniqid(),
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    expect((bool) $event->registration_required)->toBeFalse();
});
