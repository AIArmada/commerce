<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Commerce\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Features\Maps\Models\SeatSection;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Ticketing\Features\Passes\Models\Pass;

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

it('revokes unused passes and releases their seats even when the registration is already terminal', function (string $status, bool $byIds): void {
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => $status,
    ]);
    $pass = Pass::factory()->create([
        'ticketable_type' => $event->getMorphClass(),
        'ticketable_id' => $event->getKey(),
        'ticket_type_id' => $ticketType->getKey(),
        'registration_type' => $registration->getMorphClass(),
        'registration_id' => $registration->getKey(),
        'status' => 'activated',
    ]);

    $allocation = SeatAllocation::factory()->create([
        'allocated_to_type' => $pass->getMorphClass(),
        'allocated_to_id' => $pass->getKey(),
    ]);

    $driver = app(EventTicketingDriver::class);
    $revoke = fn (): int => $byIds
        ? $driver->revokePassesByIds([(string) $pass->getKey()], 'Registration terminal')
        : $driver->revokePasses($registration->getMorphClass(), (string) $registration->getKey(), 'Registration terminal');

    expect($pass->isValid())->toBeFalse()
        ->and($revoke())->toBe(1)
        ->and((string) $pass->fresh()->status)->toBe('revoked')
        ->and($allocation->fresh()->status)->toBe('released')
        ->and($revoke())->toBe(0);
})->with([
    'refunded registration' => ['refunded', false],
    'cancelled registration' => ['cancelled', false],
    'refund by pass ids' => ['refunded', true],
]);
