<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventRegistration;
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

    expect($registration->fresh()->status)->toBe('cancelled');
});
