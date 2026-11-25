<?php

declare(strict_types=1);

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Commerce\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use AIArmada\Ticketing\Models\Pass;

it('issues passes for registration items', function (): void {
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event, ['admits_quantity' => 2]);
    $registration = EventRegistration::factory()->create(['event_id' => $event->id]);
    $registration->items()->create([
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
    ]);

    $passes = app(IssueEventRegistrationPassesAction::class)->handle((string) $registration->getKey());

    expect($passes)->toHaveCount(4); // 2 items * 2 admits = 4 passes
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

    $ticketType = createEventTicketType($session, [
        'seating_mode' => SeatingMode::Assigned,
    ]);

    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
    ]);

    $registration->items()->create([
        'ticket_type_id' => $ticketType->id,
        'quantity' => 1,
    ]);

    $passes = app(IssueEventRegistrationPassesAction::class)->handle((string) $registration->getKey());
    $pass = Pass::query()->findOrFail($passes[0]);

    expect($pass->seatAllocations()->first()?->seat_id)->toBe($sessionSeat->id);
});
