<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Console\Commands\ReleaseExpiredHoldsCommand;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;

beforeEach(function () {
    $map = SeatMap::factory()->create();
    $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
    $this->seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);
});

it('releases expired holds', function () {
    SeatHold::factory()->expired()->create(['seat_id' => $this->seat->id]);
    SeatHold::factory()->create(['seat_id' => $this->seat->id]);

    $this->artisan(ReleaseExpiredHoldsCommand::class)
        ->assertSuccessful();

    expect(SeatHold::count())->toBe(1);
});

it('reports zero when no holds are expired', function () {
    SeatHold::factory()->create(['seat_id' => $this->seat->id]);

    $this->artisan(ReleaseExpiredHoldsCommand::class)
        ->assertSuccessful();

    expect(SeatHold::count())->toBe(1);
});

it('releases expired holds across owners when no request owner is active', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Seating Owner A',
        'email' => 'seating-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Seating Owner B',
        'email' => 'seating-owner-b@example.com',
        'password' => 'secret',
    ]);

    $makeSeat = function (): Seat {
        $map = SeatMap::factory()->create();
        $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);

        return Seat::factory()->available()->create(['seat_section_id' => $section->id]);
    };

    $expiredA = OwnerContext::withOwner($ownerA, fn (): SeatHold => SeatHold::factory()->expired()->create([
        'seat_id' => $makeSeat()->id,
    ]));
    $expiredB = OwnerContext::withOwner($ownerB, fn (): SeatHold => SeatHold::factory()->expired()->create([
        'seat_id' => $makeSeat()->id,
    ]));
    $activeB = OwnerContext::withOwner($ownerB, fn (): SeatHold => SeatHold::factory()->create([
        'seat_id' => $makeSeat()->id,
    ]));

    $this->artisan(ReleaseExpiredHoldsCommand::class)
        ->assertSuccessful();

    OwnerContext::withOwner(null, function () use ($expiredA, $expiredB, $activeB): void {
        expect(SeatHold::query()->withoutOwnerScope()->whereKey($expiredA->id)->exists())->toBeFalse()
            ->and(SeatHold::query()->withoutOwnerScope()->whereKey($expiredB->id)->exists())->toBeFalse()
            ->and(SeatHold::query()->withoutOwnerScope()->whereKey($activeB->id)->exists())->toBeTrue();
    });
});
