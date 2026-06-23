<?php

declare(strict_types=1);

use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Exceptions\InconsistentTicketTypePricingException;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;

it('allows free ticket type on free event', function (): void {
    $event = Event::factory()->free()->create();

    $ticketType = EventTicketType::factory()->freeTicket()->create(['event_id' => $event->id]);

    expect($ticketType->exists)->toBeTrue();
});

it('allows paid ticket type on paid event', function (): void {
    $event = Event::factory()->paid()->create();

    $ticketType = EventTicketType::factory()->create(['event_id' => $event->id, 'price' => 1500]);

    expect($ticketType->exists)->toBeTrue();
});

it('allows paid ticket type on paid occurrence scope', function (): void {
    $event = Event::factory()->free()->create();
    $occurrence = EventOccurrence::factory()->paid()->create(['event_id' => $event->id]);

    $ticketType = EventTicketType::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'price' => 1500,
    ]);

    expect($ticketType->exists)->toBeTrue();
});

it('allows paid ticket type on paid session scope', function (): void {
    $event = Event::factory()->free()->create();
    $occurrence = EventOccurrence::factory()->free()->create(['event_id' => $event->id]);
    $session = EventSession::factory()->paid()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);

    $ticketType = EventTicketType::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'price' => 1500,
    ]);

    expect($ticketType->exists)->toBeTrue();
});

it('rejects paid ticket type on free-only event', function (): void {
    $event = Event::factory()->free()->create();
    $threw = false;
    $message = '';

    try {
        EventTicketType::factory()->create(['event_id' => $event->id, 'price' => 1500]);
        $message = 'no exception thrown';
    } catch (InconsistentTicketTypePricingException $e) {
        $threw = true;
        $message = $e->getMessage();
    } catch (Throwable $e) {
        $message = get_class($e) . ': ' . $e->getMessage();
    }
    expect($threw)->toBeTrue($message);
});

it('rejects free ticket type on paid-only event', function (): void {
    $event = Event::factory()->paid()->create();
    $threw = false;
    $message = '';

    try {
        EventTicketType::factory()->freeTicket()->create(['event_id' => $event->id]);
        $message = 'no exception thrown';
    } catch (InconsistentTicketTypePricingException $e) {
        $threw = true;
        $message = $e->getMessage();
    } catch (Throwable $e) {
        $message = get_class($e) . ': ' . $e->getMessage();
    }
    expect($threw)->toBeTrue($message);
});

it('allows both free and paid ticket types on mixed event', function (): void {
    $event = Event::factory()->pricingMode(PricingMode::Mixed)->create();

    EventTicketType::factory()->freeTicket()->create(['event_id' => $event->id]);
    EventTicketType::factory()->create(['event_id' => $event->id, 'price' => 1500]);

    expect(EventTicketType::query()->where('event_id', $event->id)->count())->toBe(2);
});
