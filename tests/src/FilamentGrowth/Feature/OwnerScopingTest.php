<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\FilamentGrowth\Resources\VariantResource;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

if (! function_exists('filamentGrowth_makeOwner')) {
    function filamentGrowth_makeOwner(string $id): Model
    {
        return new class($id) extends Model
        {
            public $incrementing = false;

            protected $keyType = 'string';

            public function __construct(private readonly string $uuid) {}

            public function getKey(): mixed
            {
                return $this->uuid;
            }

            public function getMorphClass(): string
            {
                return 'tests:growth-owner';
            }
        };
    }
}

function filamentGrowth_createExperimentForOwner(Model $owner, string $name, string $code): array
{
    return OwnerContext::withOwner($owner, function () use ($code, $name): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => $name . ' Property',
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => $code,
            'name' => $name . ' Variant',
        ]);

        return [$experiment, $variant];
    });
}

beforeEach(function (): void {
    Variant::query()->delete();
    Experiment::query()->delete();
    TrackedProperty::query()->delete();
});

it('scopes ExperimentResource query to the resolved owner', function (): void {
    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000000b');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$experimentA] = filamentGrowth_createExperimentForOwner($ownerA, 'Owner A Experiment', 'A');
    [$experimentB] = filamentGrowth_createExperimentForOwner($ownerB, 'Owner B Experiment', 'B');

    $names = ExperimentResource::getEloquentQuery()->pluck('name')->all();

    expect($names)->toContain($experimentA->name)
        ->not->toContain($experimentB->name);
});

it('scopes VariantResource query to the resolved owner', function (): void {
    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000001a');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000001b');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [, $variantA] = filamentGrowth_createExperimentForOwner($ownerA, 'Variant A Experiment', 'A');
    [, $variantB] = filamentGrowth_createExperimentForOwner($ownerB, 'Variant B Experiment', 'B');

    $codes = VariantResource::getEloquentQuery()->pluck('code')->all();

    expect($codes)->toContain($variantB->code)
        ->not->toContain($variantA->code);
});