<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSeating\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerUniqueRule;
use AIArmada\FilamentSeating\Pages\SeatMapEditor;
use AIArmada\FilamentSeating\Pages\SeatMapOccupancy;
use AIArmada\FilamentSeating\Resources\SeatMapResource;
use AIArmada\FilamentSeating\Widgets\SeatMapOverview;
use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatMap as SeatMapModel;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('seating.owner.enabled', true);
    config()->set('seating.owner.include_global', false);
    config()->set('seating.owner.auto_assign_on_create', true);
});

function resolveSeatingOwner(?Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

function createSeatMapWithSeats(Model $owner, string $name, int $seats, int $blocked = 0): SeatMapModel
{
    return OwnerContext::withOwner($owner, static function () use ($name, $seats, $blocked): SeatMapModel {
        $map = SeatMapModel::query()->create(['name' => $name, 'slug' => Str::slug($name)]);
        $section = SeatSection::query()->create([
            'seat_map_id' => $map->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'capacity' => $seats,
        ]);

        for ($i = 0; $i < $seats; $i++) {
            Seat::query()->create([
                'seat_section_id' => $section->id,
                'row_label' => 'A',
                'seat_label' => 'A' . ($i + 1),
                'row_number' => 1,
                'column_number' => $i + 1,
                'status' => $i < $blocked ? SeatStatus::Blocked : SeatStatus::Available,
            ]);
        }

        return $map;
    });
}

it('counts only the resolved owner records in the overview widget', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    createSeatMapWithSeats($ownerA, 'Map A', 3, 1);
    createSeatMapWithSeats($ownerB, 'Map B', 5, 0);

    resolveSeatingOwner($ownerA);

    $widget = app(SeatMapOverview::class);
    $method = new ReflectionMethod(SeatMapOverview::class, 'getStats');
    $stats = $method->invoke($widget);

    expect($stats[0]->getValue())->toBe(1)
        ->and($stats[1]->getValue())->toBe(3)
        ->and($stats[2]->getValue())->toBe(1);
});

it('fails closed in the overview widget without an owner context', function (): void {
    app()->forgetInstance(OwnerResolverInterface::class);

    $widget = app(SeatMapOverview::class);
    $method = new ReflectionMethod(SeatMapOverview::class, 'getStats');

    expect(fn () => $method->invoke($widget))->toThrow(NoCurrentOwnerException::class);
});

it('denies seat map access without granted abilities', function (): void {
    $map = new SeatMapModel;

    expect(SeatMapResource::canViewAny())->toBeFalse()
        ->and(SeatMapResource::canView($map))->toBeFalse()
        ->and(SeatMapResource::canCreate())->toBeFalse()
        ->and(SeatMapResource::canEdit($map))->toBeFalse()
        ->and(SeatMapResource::canDelete($map))->toBeFalse()
        ->and(SeatMapResource::shouldRegisterNavigation())->toBeFalse();

    Gate::define('seat-map.viewAny', static fn (): bool => true);
    $this->be(User::factory()->create());

    expect(SeatMapResource::canViewAny())->toBeTrue()
        ->and(SeatMapResource::shouldRegisterNavigation())->toBeTrue();
});

it('rejects unknown and cross-owner seat map ids on the editor pages', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $ownMap = createSeatMapWithSeats($ownerA, 'Own Map', 1);
    $foreignMap = createSeatMapWithSeats($ownerB, 'Foreign Map', 1);

    resolveSeatingOwner($ownerA);

    foreach ([new SeatMapEditor, new SeatMapOccupancy] as $page) {
        $page->mount($ownMap->id);

        expect($page->seatMapId)->toBe($ownMap->id);

        expect(fn () => $page->mount($foreignMap->id))->toThrow(NotFoundHttpException::class)
            ->and(fn () => $page->mount((string) Str::uuid()))->toThrow(NotFoundHttpException::class);

        $page->mount(null);

        expect($page->seatMapId)->toBeNull();
    }
});

it('scopes seat map slug uniqueness to the resolved owner', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    createSeatMapWithSeats($ownerA, 'Taken Map', 1);

    resolveSeatingOwner($ownerA);

    $sameOwner = Validator::make(
        ['slug' => 'taken-map'],
        ['slug' => OwnerUniqueRule::scopeToOwner(Rule::unique('seat_maps', 'slug'), SeatMapModel::class)]
    );

    expect($sameOwner->fails())->toBeTrue();

    resolveSeatingOwner($ownerB);

    $otherOwner = Validator::make(
        ['slug' => 'taken-map'],
        ['slug' => OwnerUniqueRule::scopeToOwner(Rule::unique('seat_maps', 'slug'), SeatMapModel::class)]
    );

    expect($otherOwner->fails())->toBeFalse();
});
