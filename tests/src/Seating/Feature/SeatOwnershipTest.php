<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Contracts\SeatingMode;
use AIArmada\Seating\EntryPoints\Livewire\SeatMap as SeatMapComponent;
use AIArmada\Seating\Exceptions\InsufficientSeatsException;
use AIArmada\Seating\Exceptions\SectionCapacityExceededException;
use AIArmada\Seating\Features\Maps\Actions\DeleteSeatMap;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Features\Maps\Models\SeatSection;
use AIArmada\Seating\Features\Reservations\Actions\ConvertHoldsToAllocationsAction;
use AIArmada\Seating\Features\Reservations\Actions\EnsureSeatHoldAction;
use AIArmada\Seating\Features\Reservations\Actions\EnsureSectionAllocationAction;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Owner-scope config snapshots at model boot and the boot registry is
    // static per worker process, so clear it for deterministic per-test
    // scoping regardless of which tests ran earlier in this process.
    Model::clearBootedModels();

    $map = SeatMap::factory()->create();
    $this->section = SeatSection::factory()->create([
        'seat_map_id' => $map->id,
        'capacity' => 10,
    ]);
    $this->seat = Seat::factory()->available()->create([
        'seat_section_id' => $this->section->id,
    ]);
});

it('copies the hold owner onto the converted allocation', function (): void {
    $owner = User::query()->create([
        'name' => 'Hold Owner',
        'email' => 'hold-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $hold = OwnerContext::withOwner($owner, function (): SeatHold {
        $map = SeatMap::factory()->create();
        $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
        $seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);

        return SeatHold::factory()->create(['seat_id' => $seat->id]);
    });

    $allocations = OwnerContext::withOwner($owner, fn () => app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$hold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    ));

    $allocation = $allocations->firstOrFail();

    expect($allocation->owner_type)->toBe($hold->owner_type)
        ->and($allocation->owner_id)->toBe($hold->owner_id);
});

afterEach(function (): void {
    config()->set('seating.owner.include_global', false);
    Model::clearBootedModels();
});

it('converts a global hold to a global allocation inside explicit global scope', function (): void {
    config()->set('seating.owner.include_global', true);
    Model::clearBootedModels();

    $hold = OwnerContext::withOwner(null, function (): SeatHold {
        $map = SeatMap::factory()->create();
        $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
        $seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);

        return SeatHold::factory()->create(['seat_id' => $seat->id]);
    });

    expect($hold->owner_type)->toBeNull();

    $allocations = OwnerContext::withOwner(null, fn () => app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$hold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    ));

    $allocation = $allocations->firstOrFail();

    expect($allocation->owner_type)->toBeNull()
        ->and($allocation->owner_id)->toBeNull();
});

it('refuses to convert a global hold under an ambient owner without residue', function (): void {
    config()->set('seating.owner.include_global', true);
    Model::clearBootedModels();

    $owner = User::query()->create([
        'name' => 'Ambient Owner',
        'email' => 'ambient-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $hold = OwnerContext::withOwner(null, function (): SeatHold {
        $map = SeatMap::factory()->create();
        $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
        $seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);

        return SeatHold::factory()->create(['seat_id' => $seat->id]);
    });

    expect(fn () => OwnerContext::withOwner($owner, fn () => app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$hold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    )))->toThrow(AuthorizationException::class);

    expect(SeatAllocation::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(SeatHold::query()->withoutOwnerScope()->whereKey($hold->id)->value('converted_at'))->toBeNull();
});

it('refuses to convert another owner hold into the ambient context', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'convert-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'convert-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $hold = OwnerContext::withOwner($ownerA, function (): SeatHold {
        $map = SeatMap::factory()->create();
        $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
        $seat = Seat::factory()->available()->create(['seat_section_id' => $section->id]);

        return SeatHold::factory()->create(['seat_id' => $seat->id]);
    });

    expect(fn () => OwnerContext::withOwner($ownerB, fn () => app(ConvertHoldsToAllocationsAction::class)->handle(
        holds: [$hold],
        mode: SeatingMode::Assigned,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    )))->toThrow(ModelNotFoundException::class);

    expect(SeatAllocation::query()->withoutOwnerScope()->count())->toBe(0);
});

it('copies the section owner onto section allocations', function (): void {
    $owner = User::query()->create([
        'name' => 'Section Owner',
        'email' => 'section-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $section = OwnerContext::withOwner($owner, function (): SeatSection {
        $map = SeatMap::factory()->create();

        return SeatSection::factory()->create(['seat_map_id' => $map->id, 'capacity' => 4]);
    });

    $allocation = OwnerContext::withOwner($owner, fn (): SeatAllocation => app(EnsureSectionAllocationAction::class)->handle(
        sectionId: $section->id,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    ));

    expect($allocation->owner_type)->toBe($section->owner_type)
        ->and($allocation->owner_id)->toBe($section->owner_id);
});

it('creates a tenant allocation from a shared global section', function (): void {
    config()->set('seating.owner.include_global', true);
    Model::clearBootedModels();

    $owner = User::query()->create([
        'name' => 'Ambient Section Owner',
        'email' => 'ambient-section-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $section = OwnerContext::withOwner(null, function (): SeatSection {
        $map = SeatMap::factory()->create();

        return SeatSection::factory()->create(['seat_map_id' => $map->id, 'capacity' => 4]);
    });

    expect($section->owner_type)->toBeNull();

    $allocation = OwnerContext::withOwner($owner, fn (): SeatAllocation => app(EnsureSectionAllocationAction::class)->handle(
        sectionId: $section->id,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    ));

    expect($allocation->owner_type)->toBe($owner->getMorphClass())
        ->and((string) $allocation->owner_id)->toBe((string) $owner->getKey())
        ->and($allocation->seat_section_id)->toBe($section->id);
});

it('rejects holds for seats outside the current scope', function (): void {
    expect(fn (): SeatHold => SeatHold::factory()->create([
        'seat_id' => (string) Str::orderedUuid(),
    ]))->toThrow(InvalidArgumentException::class, 'does not exist in the current scope');

    expect(fn (): SeatHold => SeatHold::factory()->create([
        'seat_id' => null,
    ]))->toThrow(InvalidArgumentException::class, 'requires a seat_id');
});

it('rejects half-specified holder and allocation targets', function (): void {
    expect(fn (): SeatHold => SeatHold::factory()->create([
        'seat_id' => $this->seat->id,
        'held_by_type' => User::class,
        'held_by_id' => null,
    ]))->toThrow(InvalidArgumentException::class, 'Held-by type and id');

    expect(fn (): SeatAllocation => SeatAllocation::factory()->create([
        'seat_id' => $this->seat->id,
        'allocated_to_type' => 'pass',
        'allocated_to_id' => null,
    ]))->toThrow(InvalidArgumentException::class, 'Allocated-to type and id');
});

it('rejects allocations pointing at nothing or at unknown rows', function (): void {
    expect(fn (): SeatAllocation => SeatAllocation::factory()->create([
        'seat_id' => null,
        'seat_section_id' => null,
    ]))->toThrow(InvalidArgumentException::class, 'requires a seat or a section');

    expect(fn (): SeatAllocation => SeatAllocation::factory()->create([
        'seat_id' => (string) Str::orderedUuid(),
        'seat_section_id' => null,
    ]))->toThrow(InvalidArgumentException::class, 'does not exist in the current scope');

    expect(fn (): SeatAllocation => SeatAllocation::factory()->create([
        'seat_id' => null,
        'seat_section_id' => (string) Str::orderedUuid(),
    ]))->toThrow(InvalidArgumentException::class, 'does not exist in the current scope');
});

it('does not let different owners hold the same shared global seat', function (): void {
    config()->set('seating.owner.include_global', true);
    Model::clearBootedModels();

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $map = OwnerContext::withOwner(null, function (): SeatMap {
        $map = SeatMap::factory()->create();
        $section = SeatSection::factory()->create(['seat_map_id' => $map->id]);
        Seat::factory()->available()->create(['seat_section_id' => $section->id]);

        return $map;
    });

    OwnerContext::withOwner($ownerA, fn () => app(EnsureSeatHoldAction::class)->handle(
        mapId: $map->id,
        quantity: 1,
        mode: SeatingMode::Assigned,
    ));

    expect(fn () => OwnerContext::withOwner($ownerB, fn () => app(EnsureSeatHoldAction::class)->handle(
        mapId: $map->id,
        quantity: 1,
        mode: SeatingMode::Assigned,
    )))->toThrow(InsufficientSeatsException::class);

    expect(SeatHold::query()->withoutOwnerScope()->count())->toBe(1);
});

it('counts assigned allocations across owners against shared section capacity', function (): void {
    config()->set('seating.owner.include_global', true);
    Model::clearBootedModels();

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $section = OwnerContext::withOwner(null, function (): SeatSection {
        $map = SeatMap::factory()->create();
        $section = SeatSection::factory()->create(['seat_map_id' => $map->id, 'capacity' => 1]);
        Seat::factory()->available()->create(['seat_section_id' => $section->id]);

        return $section;
    });

    OwnerContext::withOwner($ownerA, function () use ($section): void {
        $holds = app(EnsureSeatHoldAction::class)->handle(
            mapId: $section->seatMap->id,
            quantity: 1,
            mode: SeatingMode::Assigned,
        );

        app(ConvertHoldsToAllocationsAction::class)->handle(
            holds: $holds,
            mode: SeatingMode::Assigned,
            allocToType: 'pass',
            allocToId: (string) Str::orderedUuid(),
        );
    });

    expect(fn () => OwnerContext::withOwner($ownerB, fn () => app(EnsureSectionAllocationAction::class)->handle(
        sectionId: $section->id,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    )))->toThrow(SectionCapacityExceededException::class);

    expect(SeatAllocation::query()->withoutOwnerScope()->count())->toBe(1);
});

it('isolates cached layouts for different owners viewing a shared seat map', function (): void {
    config()->set('seating.owner.include_global', true);
    Model::clearBootedModels();

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $map = OwnerContext::withOwner(null, fn (): SeatMap => SeatMap::factory()->create());

    $createSeat = function (User $owner, string $code) use ($map): Seat {
        return OwnerContext::withOwner($owner, function () use ($map, $code): Seat {
            $section = SeatSection::factory()->create(['seat_map_id' => $map->id, 'code' => $code]);

            return Seat::factory()->available()->create(['seat_section_id' => $section->id]);
        });
    };

    $seatA = $createSeat($ownerA, 'PRIVATE-A');
    $seatB = $createSeat($ownerB, 'PRIVATE-B');

    $layoutFor = function (User $owner) use ($map): array {
        return OwnerContext::withOwner($owner, function () use ($map): array {
            $component = new SeatMapComponent;
            $component->mount(seatMapId: $map->id);

            return $component->getLayoutProperty();
        });
    };

    expect(array_column($layoutFor($ownerA)['seats'], 'id'))->toBe([$seatA->id])
        ->and(array_column($layoutFor($ownerB)['seats'], 'id'))->toBe([$seatB->id])
        ->and(array_column($layoutFor($ownerA)['seats'], 'id'))->toBe([$seatA->id]);
});

it('refuses to delete a shared map that has cross-owner sections', function (): void {
    config()->set('seating.owner.include_global', true);
    Model::clearBootedModels();

    $owner = User::factory()->create();
    $map = OwnerContext::withOwner(null, fn (): SeatMap => SeatMap::factory()->create());
    $section = OwnerContext::withOwner($owner, fn (): SeatSection => SeatSection::factory()->create([
        'seat_map_id' => $map->getKey(),
    ]));

    expect(fn () => OwnerContext::withOwner(null, fn () => app(DeleteSeatMap::class)->handle($map)))
        ->toThrow(AuthorizationException::class);

    expect(SeatMap::query()->withoutOwnerScope()->whereKey($map->getKey())->exists())->toBeTrue()
        ->and(SeatSection::query()->withoutOwnerScope()->whereKey($section->getKey())->exists())->toBeTrue();
});
