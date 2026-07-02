<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventPassIssuer;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;

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

it('prefers session seat maps over event seat maps', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);

    $createSeatFor = function (Event | EventSession $host, string $sectionCode): Seat {
        $map = SeatMap::factory()->create([
            'seatable_type' => $host->getMorphClass(),
            'seatable_id' => $host->getKey(),
        ]);
        $section = SeatSection::factory()->create([
            'seat_map_id' => $map->id,
            'code' => $sectionCode,
        ]);

        return Seat::factory()->available()->create([
            'seat_section_id' => $section->id,
            'row_label' => $sectionCode,
            'row_number' => 1,
            'column_number' => 1,
            'seat_label' => '1',
        ]);
    };

    $createSeatFor($event, 'EVENT');
    $sessionSeat = $createSeatFor($session, 'SESSION');

    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
    ]);

    $passes = app(EventPassIssuer::class)->issuePassesFor($registration->fresh());
    $pass = collect($passes)->first();

    expect($pass->seatAllocations()->first()?->seat_id)->toBe($sessionSeat->id);
});
