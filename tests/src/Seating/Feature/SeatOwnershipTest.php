<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Actions\ConvertHoldsToAllocationsAction;
use AIArmada\Seating\Actions\EnsureSectionAllocationAction;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
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
        section: $section,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    ));

    expect($allocation->owner_type)->toBe($section->owner_type)
        ->and($allocation->owner_id)->toBe($section->owner_id);
});

it('keeps a global section global when allocating under an ambient owner', function (): void {
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
        section: $section,
        allocToType: 'pass',
        allocToId: (string) Str::orderedUuid(),
    ));

    expect($allocation->owner_type)->toBeNull()
        ->and($allocation->owner_id)->toBeNull();
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
