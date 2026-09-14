<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Seating\Actions\EnsureSeatHoldAction;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use AIArmada\Seating\Services\DefaultSeatAllocator;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    // Owner-scope config snapshots at model boot and the boot registry is
    // static per worker process, so clear it for deterministic per-test
    // scoping regardless of which tests ran earlier in this process.
    Model::clearBootedModels();

    $this->map = SeatMap::factory()->create();
    $this->section = SeatSection::factory()->create([
        'seat_map_id' => $this->map->id,
        'code' => 'A',
    ]);

    foreach (['standard', 'standard', 'vip', 'standard', 'vip'] as $index => $category) {
        Seat::factory()->available()->create([
            'seat_section_id' => $this->section->id,
            'row_label' => 'A',
            'row_number' => $index + 1,
            'column_number' => 1,
            'seat_label' => (string) ($index + 1),
            'category' => $category,
        ]);
    }
});

it('fills preferred categories before other seats in one pass', function (): void {
    $holds = app(EnsureSeatHoldAction::class)->handle(
        map: $this->map,
        quantity: 3,
        mode: SeatingMode::Assigned,
        categoryPreferences: ['vip'],
    );

    $categories = $holds->map(fn (SeatHold $hold): ?string => $hold->seat->category)->all();

    expect($holds)->toHaveCount(3)
        ->and($categories)->toBe(['vip', 'vip', 'standard']);
});

it('keeps category preference order identical between the action and the allocator', function (): void {
    $actionHolds = app(EnsureSeatHoldAction::class)->handle(
        map: $this->map,
        quantity: 2,
        mode: SeatingMode::Assigned,
        categoryPreferences: ['vip'],
    );

    $actionSeatIds = $actionHolds->map(fn (SeatHold $hold): string => $hold->seat_id)->all();

    SeatHold::query()->delete();

    $results = app(DefaultSeatAllocator::class)->allocate(
        map: $this->map,
        quantity: 2,
        categoryPreferences: ['vip'],
    );

    expect($results->pluck('seatId')->all())->toBe($actionSeatIds)
        ->and($results->pluck('category')->all())->toBe(['vip', 'vip']);
});

it('never returns seats that gained an active hold', function (): void {
    $firstSeat = Seat::query()->orderBy('row_number')->firstOrFail();
    SeatHold::factory()->create(['seat_id' => $firstSeat->id]);

    $holds = app(EnsureSeatHoldAction::class)->handle(
        map: $this->map,
        quantity: 4,
        mode: SeatingMode::Assigned,
    );

    expect($holds->pluck('seat_id')->all())->not->toContain($firstSeat->id)
        ->and($holds)->toHaveCount(4);
});

it('rejects a half-specified holder instead of storing it', function (): void {
    expect(fn (): mixed => app(EnsureSeatHoldAction::class)->handle(
        map: $this->map,
        quantity: 1,
        mode: SeatingMode::Assigned,
        heldByType: User::class,
        heldById: null,
    ))->toThrow(InvalidArgumentException::class, 'Held-by type and id');

    expect(fn (): mixed => app(DefaultSeatAllocator::class)->allocate(
        map: $this->map,
        quantity: 1,
        heldByType: null,
        heldById: 'some-id',
    ))->toThrow(InvalidArgumentException::class, 'Held-by type and id');
});

it('requires an owner context when owner mode is enabled', function (): void {
    config()->set('seating.owner.enabled', true);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    expect(fn (): mixed => app(EnsureSeatHoldAction::class)->handle(
        map: $this->map,
        quantity: 1,
        mode: SeatingMode::Assigned,
    ))->toThrow(NoCurrentOwnerException::class);
});

it('creates global holds inside explicit global scope', function (): void {
    config()->set('seating.owner.enabled', true);

    $map = OwnerContext::withOwner(null, function (): SeatMap {
        $map = SeatMap::factory()->create();
        $section = SeatSection::factory()->create(['seat_map_id' => $map->id, 'code' => 'G']);

        Seat::factory()->available()->count(2)->create([
            'seat_section_id' => $section->id,
            'row_label' => 'G',
            'row_number' => 1,
            'column_number' => 1,
            'seat_label' => '1',
        ]);

        return $map;
    });

    $holds = OwnerContext::withOwner(null, fn () => app(EnsureSeatHoldAction::class)->handle(
        map: $map,
        quantity: 2,
        mode: SeatingMode::Assigned,
    ));

    expect($holds)->toHaveCount(2)
        ->and($holds->every(fn (SeatHold $hold): bool => $hold->owner_type === null && $hold->owner_id === null))->toBeTrue();
});
