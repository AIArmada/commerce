<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventSeatingDriver;
use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Commerce\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Seating\Contracts\SeatingMode;
use AIArmada\Seating\Exceptions\SeatSectionNotFoundException;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Features\Maps\Models\SeatSection;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketTypeSeatingOption;
use Illuminate\Support\Str;

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

    app(EventSeatingDriver::class)->allocatePassSeats((string) $pass->getKey());

    expect($pass->seatAllocations()->where('status', 'active')->count())->toBe(1);
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

it('does not allocate seats for a revoked pass on delayed issuance', function (): void {
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event, ['seating_mode' => SeatingMode::Assigned]);
    $map = SeatMap::factory()->create([
        'seatable_type' => $event->getMorphClass(),
        'seatable_id' => $event->getKey(),
    ]);
    $section = SeatSection::factory()->create(['seat_map_id' => $map->getKey()]);
    Seat::factory()->available()->create(['seat_section_id' => $section->getKey()]);

    $pass = Pass::factory()->create([
        'ticketable_type' => $event->getMorphClass(),
        'ticketable_id' => $event->getKey(),
        'ticket_type_id' => $ticketType->getKey(),
        'status' => 'revoked',
    ]);

    app(EventSeatingDriver::class)->allocatePassSeats((string) $pass->getKey());

    expect($pass->seatAllocations()->count())->toBe(0);
});

it('uses the same parent seat map for session holds and allocations', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->getKey()]);
    $session = EventSession::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
    ]);
    $map = SeatMap::factory()->create([
        'seatable_type' => $event->getMorphClass(),
        'seatable_id' => $event->getKey(),
    ]);
    $section = SeatSection::factory()->create(['seat_map_id' => $map->getKey()]);
    $seat = Seat::factory()->available()->create(['seat_section_id' => $section->getKey()]);
    $otherSeat = Seat::factory()->available()->create([
        'seat_section_id' => $section->getKey(),
        'row_number' => $seat->row_number + 1,
    ]);
    $ticketType = createEventTicketType($session, ['seating_mode' => SeatingMode::Assigned]);
    $driver = app(EventSeatingDriver::class);
    $sessionId = (string) $session->getKey();

    expect($driver->hasActiveSeatMap('session', $sessionId))->toBeTrue()
        ->and($driver->resolveScopeSeatMapId('session', $sessionId))->toBe((string) $map->getKey());

    $holds = $driver->holdSeats('session', $sessionId, SeatingMode::Assigned->value, 1);
    expect($holds)->toHaveCount(1)
        ->and(SeatHold::query()->findOrFail($holds[0])->seat_id)->toBe($seat->getKey());

    $allocations = $driver->allocateSeats('session', $sessionId, SeatingMode::Assigned->value, (string) $ticketType->getKey(), 'pass', (string) Str::uuid());
    expect($allocations)->toHaveCount(1)
        ->and(SeatAllocation::query()->findOrFail($allocations[0])->seat_id)->toBe($otherSeat->getKey());
});

it('rejects a general admission section from an unrelated seat map', function (): void {
    $event = Event::factory()->create();
    SeatMap::factory()->create([
        'seatable_type' => $event->getMorphClass(),
        'seatable_id' => $event->getKey(),
    ]);
    $foreignSection = SeatSection::factory()->create();
    $ticketType = createEventTicketType($event, ['seating_mode' => SeatingMode::GeneralAdmission]);
    TicketTypeSeatingOption::factory()->create([
        'ticket_type_id' => $ticketType->getKey(),
        'seat_section_id' => $foreignSection->getKey(),
    ]);

    expect(fn () => app(EventSeatingDriver::class)->allocateSeats(
        'event',
        (string) $event->getKey(),
        SeatingMode::GeneralAdmission->value,
        (string) $ticketType->getKey(),
        'pass',
        (string) Str::uuid(),
    ))->toThrow(SeatSectionNotFoundException::class);

    expect(SeatAllocation::query()->count())->toBe(0);
});

it('chooses an eligible seating option for the current map across all configured options', function (): void {
    $event = Event::factory()->create();
    $map = SeatMap::factory()->create([
        'seatable_type' => $event->getMorphClass(),
        'seatable_id' => $event->getKey(),
    ]);
    $validSection = SeatSection::factory()->create([
        'seat_map_id' => $map->getKey(),
        'capacity' => 4,
    ]);
    $foreignSection = SeatSection::factory()->create();
    $ticketType = createEventTicketType($event, ['seating_mode' => SeatingMode::GeneralAdmission]);

    TicketTypeSeatingOption::factory()->create([
        'ticket_type_id' => $ticketType->getKey(),
        'seat_section_id' => $foreignSection->getKey(),
    ]);
    TicketTypeSeatingOption::factory()->create([
        'ticket_type_id' => $ticketType->getKey(),
        'seat_section_id' => $validSection->getKey(),
    ]);

    $allocations = app(EventSeatingDriver::class)->allocateSeats(
        'event',
        (string) $event->getKey(),
        SeatingMode::GeneralAdmission->value,
        (string) $ticketType->getKey(),
        'pass',
        (string) Str::uuid(),
    );

    expect($allocations)->toHaveCount(1)
        ->and(SeatAllocation::query()->findOrFail($allocations[0])->seat_section_id)->toBe($validSection->getKey());
});
