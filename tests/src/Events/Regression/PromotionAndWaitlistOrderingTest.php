<?php

declare(strict_types=1);

use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\EventRegistrationNotAvailableException;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Registrations\Actions\PromoteWaitlistedRegistrationsAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('promotes a waitlisted registration only when capacity remains', function (): void {
    $event = Event::factory()->free()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->getKey(),
        'capacity' => 1,
    ]);

    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'status' => 'waitlisted',
        'waitlisted_at' => now()->subMinute(),
    ]);

    app(PromoteWaitlistedRegistrationsAction::class)->handle((string) $registration->getKey());

    expect($registration->fresh()->status->getValue())->toBe('confirmed')
        ->and($registration->fresh()->waitlisted_at)->toBeNull();

    $second = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'status' => 'waitlisted',
        'waitlisted_at' => now(),
    ]);

    expect(fn () => app(PromoteWaitlistedRegistrationsAction::class)->handle((string) $second->getKey()))
        ->toThrow(EventCapacityExceededException::class);

    expect($second->fresh()->status->getValue())->toBe('waitlisted');
});

it('promotes a scope in waitlist order and stops at the first request that no longer fits', function (): void {
    $event = Event::factory()->free()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->getKey(),
        'capacity' => 1,
    ]);

    $first = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'status' => 'waitlisted',
        'waitlisted_at' => now()->subMinutes(2),
    ]);
    $second = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'status' => 'waitlisted',
        'waitlisted_at' => now()->subMinute(),
    ]);

    $promoted = app(PromoteWaitlistedRegistrationsAction::class)->handleScope(
        'occurrence',
        (string) $occurrence->getKey(),
    );

    expect($promoted)->toBe(1)
        ->and($first->fresh()->status->getValue())->toBe('confirmed')
        ->and($second->fresh()->status->getValue())->toBe('waitlisted');
});

it('refuses waitlist promotion when the occurrence lifecycle no longer accepts registrations', function (): void {
    $event = Event::factory()->free()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->getKey(),
        'status' => 'cancelled',
        'cancelled_at' => now(),
    ]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'status' => 'waitlisted',
        'waitlisted_at' => now(),
    ]);

    expect(fn () => app(PromoteWaitlistedRegistrationsAction::class)->handle((string) $registration->getKey()))
        ->toThrow(EventRegistrationNotAvailableException::class);

    expect($registration->fresh()->status->getValue())->toBe('waitlisted');
});
