<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventPassIssuer;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventTicketType;

it('issues passes for registration items', function (): void {
    $event = Event::factory()->create();
    $ticketType = EventTicketType::factory()->create([
        'event_id' => $event->id,
        'admits_quantity' => 2,
    ]);
    $registration = EventRegistration::factory()->create(['event_id' => $event->id]);
    $item = $registration->items()->create([
        'event_ticket_type_id' => $ticketType->id,
        'quantity' => 2,
    ]);

    $passes = app(EventPassIssuer::class)->issuePassesFor($registration->fresh());

    expect(iterator_count($passes))->toBe(4); // 2 items * 2 admits = 4 passes
});
