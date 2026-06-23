<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;

it('creates individual registration', function (): void {
    $event = Event::factory()->create();
    $ticketType = EventTicketType::factory()->create(['event_id' => $event->id]);

    $registration = app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'participants' => [
            ['name' => 'John Doe', 'is_primary' => true],
        ],
        'items' => [
            ['event_ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
    ]);

    expect($registration->id)->not->toBeNull();
    expect($registration->registration_no)->toStartWith('REG-');
    expect($registration->registered_at)->not->toBeNull();
    expect($registration->participants)->toHaveCount(1);
    expect($registration->items)->toHaveCount(1);
});

it('cancels registration without deletion', function (): void {
    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create(['event_id' => $event->id]);

    app(RegistrationServiceInterface::class)->cancel($registration, 'Changed mind');

    expect($registration->fresh()->status->getValue())->toBe('cancelled');
});

it('creates order-item registrations with session scope', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);

    app(RegistrationServiceInterface::class)->createFromOrderItem([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'registration_type' => 'individual',
        'quantity' => 1,
    ]);

    $registration = EventRegistration::query()->latest('created_at')->first();

    expect($registration)->not->toBeNull()
        ->and($registration?->event_session_id)->toBe($session->id)
        ->and($registration?->event_occurrence_id)->toBe($occurrence->id);
});
