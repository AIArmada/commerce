<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Relations\Relation;

beforeEach(function (): void {
    Relation::morphMap(['workshop' => User::class]);
});

it('casts seating_mode to SeatingMode enum', function (): void {
    $ticketType = TicketType::factory()->create([
        'seating_mode' => 'assigned',
        'price' => 0,
    ]);

    expect($ticketType->seating_mode)->toBeInstanceOf(SeatingMode::class);
    expect($ticketType->seating_mode)->toBe(SeatingMode::Assigned);
});

it('allows null seating_mode', function (): void {
    $ticketType = TicketType::factory()->create([
        'seating_mode' => null,
        'price' => 0,
    ]);

    expect($ticketType->seating_mode)->toBeNull();
});

it('allows each SeatingMode value', function (): void {
    foreach (SeatingMode::cases() as $i => $mode) {
        $tt = TicketType::factory()->create([
            'seating_mode' => $mode->value,
            'price' => 0,
            'name' => 'seat-cast-' . $i,
            'code' => 'seat-cast-' . $i,
        ]);

        expect($tt->seating_mode)->toBe($mode);
    }
});
