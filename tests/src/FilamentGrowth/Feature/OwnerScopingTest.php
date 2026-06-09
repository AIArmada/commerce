<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\FilamentGrowth\Resources\VariantResource;
use AIArmada\Growth\Models\Assignment;
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

function filamentGrowth_createGlobalExperiment(string $name, string $code): array
{
    return OwnerContext::withOwner(null, function () use ($code, $name): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => $name . ' Property',
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => $code,
            'name' => $name . ' Variant',
        ]);

        return [$experiment, $variant];
    });
}

beforeEach(function (): void {
    filament()->setCurrentPanel('admin');

    Variant::query()->delete();
    Experiment::query()->delete();
    TrackedProperty::query()->delete();

    $this->actingAs(User::query()->create([
        'name' => 'Filament Growth Owner Scope Actor ' . Str::random(6),
        'email' => 'filament-growth-owner-scope-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]));
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

it('scopes ExperimentResource queries and mutation gates by accessible tracked properties when growth owner scoping is disabled', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000000c');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000000d');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$experimentA] = filamentGrowth_createExperimentForOwner($ownerA, 'Signals Scoped Experiment A', 'A');
    [$experimentB] = filamentGrowth_createExperimentForOwner($ownerB, 'Signals Scoped Experiment B', 'B');

    $names = ExperimentResource::getEloquentQuery()->pluck('name')->all();

    expect($names)->toContain($experimentB->name)
        ->not->toContain($experimentA->name)
        ->and(ExperimentResource::canEdit($experimentA))->toBeFalse()
        ->and(ExperimentResource::canDelete($experimentA))->toBeFalse()
        ->and(ExperimentResource::canEdit($experimentB))->toBeTrue();
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

it('scopes VariantResource queries and experiment helpers by accessible tracked properties when growth owner scoping is disabled', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000001c');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000001d');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$experimentA, $variantA] = filamentGrowth_createExperimentForOwner($ownerA, 'Signals Scoped Variant Experiment A', 'A');
    [$experimentB, $variantB] = filamentGrowth_createExperimentForOwner($ownerB, 'Signals Scoped Variant Experiment B', 'B');

    $codes = VariantResource::getEloquentQuery()->pluck('code')->all();
    // selectedExperimentModuleType is now public on VariantForm

    expect($codes)->toContain($variantB->code)
        ->not->toContain($variantA->code)
        ->and(VariantResource::canEdit($variantA))->toBeFalse()
        ->and(VariantResource::canDelete($variantA))->toBeFalse()
        ->and((string) VariantForm::selectedExperimentModuleType( (string) $experimentA->getKey()))->toBe('')
        ->and((string) VariantForm::selectedExperimentModuleType( (string) $experimentB->getKey()))->toBe((string) $experimentB->module_type);
});

it('includes global experiments when growth owner include_global is enabled', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000002a');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000002b');

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
    [$globalExperiment] = filamentGrowth_createGlobalExperiment('Global Experiment', 'G');

    $names = ExperimentResource::getEloquentQuery()->pluck('name')->all();

    expect($names)->toContain($experimentA->name)
        ->and($names)->toContain($globalExperiment->name)
        ->and($names)->not->toContain($experimentB->name);
});

it('includes global variants when growth owner include_global is enabled', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000003a');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000003b');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [, $variantA] = filamentGrowth_createExperimentForOwner($ownerA, 'Owner A Variant Experiment', 'A');
    [, $variantB] = filamentGrowth_createExperimentForOwner($ownerB, 'Owner B Variant Experiment', 'B');
    [, $globalVariant] = filamentGrowth_createGlobalExperiment('Global Variant Experiment', 'G');

    $codes = VariantResource::getEloquentQuery()->pluck('code')->all();

    expect($codes)->toContain($variantA->code)
        ->and($codes)->toContain($globalVariant->code)
        ->and($codes)->not->toContain($variantB->code);
});

it('does not expose global tracked properties or experiments as writable create targets when signals include_global is enabled and growth owner scoping is disabled', function (): void {
    config()->set('growth.features.owner.enabled', false);
    config()->set('signals.owner.include_global', true);

    $owner = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000003c');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$globalExperiment, $globalVariant] = filamentGrowth_createGlobalExperiment('Signals Include Global Experiment', 'GI');
    $trackedPropertyScopeMethod = new ReflectionMethod(ExperimentResource::class, 'scopeTrackedPropertyQueryToCurrentOwner');
    $trackedPropertyScopeMethod->setAccessible(true);
    $experimentScopeMethod = new ReflectionMethod(VariantResource::class, 'scopeExperimentQueryToCurrentOwner');
    $experimentScopeMethod->setAccessible(true);

    $writableTrackedPropertyIds = $trackedPropertyScopeMethod->invoke(null, TrackedProperty::query())->pluck('id')->all();
    $writableExperimentIds = $experimentScopeMethod->invoke(null, Experiment::query())->pluck('id')->all();

    expect(ExperimentResource::canCreate())->toBeFalse()
        ->and(VariantResource::canCreate())->toBeFalse()
        ->and($writableTrackedPropertyIds)->not->toContain((string) $globalExperiment->tracked_property_id)
        ->and($writableExperimentIds)->not->toContain((string) $globalExperiment->getKey());
});

it('treats global experiments as read-only unless the current context is explicitly global', function (): void {
    config()->set('growth.features.owner.include_global', true);

    [$globalExperiment] = filamentGrowth_createGlobalExperiment('Read Only Global Experiment', 'G');

    expect(ExperimentResource::canEdit($globalExperiment))->toBeFalse()
        ->and(ExperimentResource::canDelete($globalExperiment))->toBeFalse();

    OwnerContext::withOwner(null, function () use ($globalExperiment): void {
        expect(ExperimentResource::canEdit($globalExperiment))->toBeTrue()
            ->and(ExperimentResource::canDelete($globalExperiment))->toBeTrue();
    });
});

it('treats global variants as read-only unless the current context is explicitly global', function (): void {
    config()->set('growth.features.owner.include_global', true);

    [, $globalVariant] = filamentGrowth_createGlobalExperiment('Read Only Global Variant', 'G');

    expect(VariantResource::canEdit($globalVariant))->toBeFalse()
        ->and(VariantResource::canDelete($globalVariant))->toBeFalse();

    OwnerContext::withOwner(null, function () use ($globalVariant): void {
        expect(VariantResource::canEdit($globalVariant))->toBeTrue()
            ->and(VariantResource::canDelete($globalVariant))->toBeTrue();
    });
});

it('fails closed for experiment mutations when the tracked property drifts out of the writable scope', function (): void {
    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000003d');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000003e');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$experiment] = filamentGrowth_createExperimentForOwner($ownerA, 'Drifted Filament Experiment', 'D');

    $foreignTrackedProperty = OwnerContext::withOwner($ownerB, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Foreign Drifted Property',
        'slug' => 'foreign-drifted-property-' . Str::lower(Str::random(6)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    DB::table((new Experiment)->getTable())
        ->where('id', $experiment->getKey())
        ->update(['tracked_property_id' => $foreignTrackedProperty->getKey()]);

    $corruptExperiment = Experiment::query()->findOrFail($experiment->getKey());

    expect(ExperimentResource::canEdit($corruptExperiment))->toBeFalse()
        ->and(ExperimentResource::canDelete($corruptExperiment))->toBeFalse();
});

it('removes experiments and variants from readable queries when the tracked property drifts out of the experiment owner tuple', function (): void {
    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000003g');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000003h');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$experiment, $variant] = filamentGrowth_createExperimentForOwner($ownerA, 'Readable Drifted Experiment', 'RD');

    $foreignTrackedProperty = OwnerContext::withOwner($ownerB, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Readable Drifted Foreign Property',
        'slug' => 'readable-drifted-foreign-property-' . Str::lower(Str::random(6)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    DB::table((new Experiment)->getTable())
        ->where('id', $experiment->getKey())
        ->update(['tracked_property_id' => $foreignTrackedProperty->getKey()]);

    expect(ExperimentResource::getEloquentQuery()->whereKey($experiment->getKey())->exists())->toBeFalse()
        ->and(VariantResource::getEloquentQuery()->whereKey($variant->getKey())->exists())->toBeFalse();
});

it('scopes variant code uniqueness to writable experiments only', function (): void {
    config()->set('growth.features.owner.enabled', false);
    config()->set('signals.owner.include_global', true);

    $owner = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000003f');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$ownedExperiment, $ownedVariant] = filamentGrowth_createExperimentForOwner($owner, 'Owned Variant Code Experiment', 'OWN');
    [$globalExperiment, $globalVariant] = filamentGrowth_createGlobalExperiment('Global Variant Code Experiment', 'GLB');
    $method = new ReflectionMethod(VariantResource::class, 'scopeCodeUniquenessToExperiment');
    $method->setAccessible(true);

    $ownedScope = $method->invoke(
        null,
        Variant::query()->where('code', $ownedVariant->code),
        (string) $ownedExperiment->getKey(),
    );

    $globalScope = $method->invoke(
        null,
        Variant::query()->where('code', $globalVariant->code),
        (string) $globalExperiment->getKey(),
    );

    expect($ownedScope->exists())->toBeTrue()
        ->and($globalScope->exists())->toBeFalse();
});

it('rejects mixed experiment bulk deletes before deleting any selected records', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $owner = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000004a');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$ownedExperiment] = filamentGrowth_createExperimentForOwner($owner, 'Owned Bulk Delete Experiment', 'O');
    [$globalExperiment] = filamentGrowth_createGlobalExperiment('Global Bulk Delete Experiment', 'G');
    $method = new ReflectionMethod(ExperimentResource::class, 'deleteSelectedExperiments');
    $method->setAccessible(true);

    expect(fn (): mixed => VariantForm::selectedExperimentModuleType( collect([$ownedExperiment, $globalExperiment])))
        ->toThrow(RuntimeException::class, 'Global growth experiments can only be deleted from explicit global context.');

    expect(Experiment::query()->withoutOwnerScope()->whereKey($ownedExperiment->getKey())->exists())->toBeTrue()
        ->and(Experiment::query()->withoutOwnerScope()->whereKey($globalExperiment->getKey())->exists())->toBeTrue();
});

it('rejects mixed variant bulk deletes before deleting any selected records', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $owner = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000005a');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [, $ownedVariant] = filamentGrowth_createExperimentForOwner($owner, 'Owned Bulk Delete Variant', 'O');
    [, $globalVariant] = filamentGrowth_createGlobalExperiment('Global Bulk Delete Variant', 'G');
    $method = new ReflectionMethod(VariantResource::class, 'deleteSelectedVariants');
    $method->setAccessible(true);

    expect(fn (): mixed => VariantForm::selectedExperimentModuleType( collect([$ownedVariant, $globalVariant])))
        ->toThrow(RuntimeException::class, 'Global growth variants can only be deleted from explicit global context.');

    expect(Variant::query()->withoutOwnerScope()->whereKey($ownedVariant->getKey())->exists())->toBeTrue()
        ->and(Variant::query()->withoutOwnerScope()->whereKey($globalVariant->getKey())->exists())->toBeTrue();
});

it('fails closed when helper lookups receive a foreign experiment record object', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000006a');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000006b');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$foreignExperiment] = filamentGrowth_createExperimentForOwner($ownerB, 'Foreign Helper Experiment', 'F');

    expect(OwnerUiScope::findForRecordOwner(TrackedProperty::class, $foreignExperiment, $foreignExperiment->tracked_property_id))->toBeNull()
        ->and(OwnerUiScope::applyForRecordOwner(Variant::query(), $foreignExperiment)->count())->toBe(0);
});

it('scopes tracked property helper queries to the resolved owner when signals owner scoping is disabled', function (): void {
    config()->set('signals.owner.enabled', false);

    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000006c');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000006d');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $propertyA = OwnerContext::withOwner($ownerA, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Owner A Scoped Property',
        'slug' => 'owner-a-scoped-property-' . Str::lower(Str::random(6)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
    ]));

    $propertyB = OwnerContext::withOwner($ownerB, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Owner B Scoped Property',
        'slug' => 'owner-b-scoped-property-' . Str::lower(Str::random(6)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]));

    $method = new ReflectionMethod(ExperimentResource::class, 'scopeTrackedPropertyQueryToCurrentOwner');
    $method->setAccessible(true);

    $propertyIds = VariantForm::selectedExperimentModuleType( TrackedProperty::query())
        ->pluck('id')
        ->all();

    expect($propertyIds)->toContain((string) $propertyA->getKey())
        ->and($propertyIds)->not->toContain((string) $propertyB->getKey());
});

it('uses the resolved owner scope key for experiment slug validation when growth owner scoping is disabled but tracked properties remain owner scoped', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $owner = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000007a');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $method = new ReflectionMethod(ExperimentResource::class, 'ownerScopeKey');
    $method->setAccessible(true);

    expect($method->invoke(null))->toBe(OwnerScopeKey::forOwner($owner));
});

it('does not resolve foreign tracked properties for experiment resources when growth owner scoping is disabled but signals owner scoping remains enabled', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000007c');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000007d');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$experimentA] = filamentGrowth_createExperimentForOwner($ownerA, 'Signals Enabled Resource Experiment', 'R');
    $method = new ReflectionMethod(ExperimentResource::class, 'findTrackedPropertyForExperiment');
    $method->setAccessible(true);

    expect(VariantForm::selectedExperimentModuleType( $experimentA))->toBeNull();
});

it('memoizes the selected experiment module type for repeated variant form visibility checks within one request', function (): void {
    $owner = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000007b');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$experiment] = filamentGrowth_createExperimentForOwner($owner, 'Memoized Module Experiment', 'M');
    // selectedExperimentModuleType is now public on VariantForm
    $method->setAccessible(true);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $firstModuleType = VariantForm::selectedExperimentModuleType( (string) $experiment->getKey());
    $secondModuleType = VariantForm::selectedExperimentModuleType( (string) $experiment->getKey());

    $moduleTypeQueryCount = collect(DB::getQueryLog())
        ->filter(function (array $query): bool {
            $sql = mb_strtolower((string) ($query['query'] ?? ''));

            return str_contains($sql, mb_strtolower((new Experiment)->getTable()))
                && str_contains($sql, 'where');
        })
        ->count();

    expect($firstModuleType)->toBe((string) $experiment->module_type)
        ->and($secondModuleType)->toBe((string) $experiment->module_type)
        ->and($moduleTypeQueryCount)->toBe(1);
});

it('does not reuse cached experiment module types across owner scope changes in the same request', function (): void {
    $ownerA = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000008a');
    $ownerB = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000008b');

    [$experimentA] = filamentGrowth_createExperimentForOwner($ownerA, 'Scoped Cache Experiment A', 'A');
    // selectedExperimentModuleType is now public on VariantForm
    $method->setAccessible(true);

    $moduleTypeForOwnerA = OwnerContext::withOwner($ownerA, fn (): mixed => VariantForm::selectedExperimentModuleType( (string) $experimentA->getKey()));
    $moduleTypeForOwnerB = OwnerContext::withOwner($ownerB, fn (): mixed => VariantForm::selectedExperimentModuleType( (string) $experimentA->getKey()));

    expect($moduleTypeForOwnerA)->toBe((string) $experimentA->module_type)
        ->and($moduleTypeForOwnerB)->toBeNull();
});

it('uses distinct selected experiment module type cache keys for unresolved and explicit global contexts', function (): void {
    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    
    $cacheKeyMethod->setAccessible(true);

    $unresolvedKey = $cacheKeyMethod->invoke(null);
    $explicitGlobalKey = OwnerContext::withOwner(
        null,
        fn (): mixed => $cacheKeyMethod->invoke(null),
    );

    expect($unresolvedKey)->toBe(OwnerScopeKey::GLOBAL . '::unresolved')
        ->and($explicitGlobalKey)->toBe(OwnerScopeKey::GLOBAL . '::explicit')
        ->and($explicitGlobalKey)->not->toBe($unresolvedKey);
});

it('counts only owner-matched child rows for readable global experiments in the experiment resource query', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $owner = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000008c');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$globalExperiment, $globalVariant] = filamentGrowth_createGlobalExperiment('Owner Safe Count Experiment', 'G');
    $timestamp = now();
    $strayVariantId = (string) Str::uuid();

    DB::table((new Assignment)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'experiment_id' => $globalExperiment->getKey(),
        'variant_id' => $globalVariant->getKey(),
        'signal_identity_id' => null,
        'signal_session_id' => null,
        'owner_type' => null,
        'owner_id' => null,
        'subject_key' => 'anonymous:resource-global-count',
        'bucket' => 0,
        'metadata' => json_encode([]),
        'assigned_at' => $timestamp,
        'first_exposed_at' => $timestamp,
        'last_seen_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table((new Variant)->getTable())->insert([
        'id' => $strayVariantId,
        'experiment_id' => $globalExperiment->getKey(),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'code' => 'X',
        'name' => 'Stray Variant',
        'description' => null,
        'traffic_percentage' => 100,
        'position' => 99,
        'is_control' => false,
        'is_active' => true,
        'settings' => json_encode([]),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table((new Assignment)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'experiment_id' => $globalExperiment->getKey(),
        'variant_id' => $strayVariantId,
        'signal_identity_id' => null,
        'signal_session_id' => null,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'subject_key' => 'anonymous:resource-stray-count',
        'bucket' => 0,
        'metadata' => json_encode([]),
        'assigned_at' => $timestamp,
        'first_exposed_at' => $timestamp,
        'last_seen_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $record = ExperimentResource::getEloquentQuery()
        ->whereKey($globalExperiment->getKey())
        ->first();

    expect($record)->toBeInstanceOf(Experiment::class)
        ->and((int) $record?->getAttribute('variants_count'))->toBe(1)
        ->and((int) $record?->getAttribute('assignments_count'))->toBe(1)
        ->and((string) $globalVariant->experiment_id)->toBe((string) $globalExperiment->getKey());
});

it('finds readable global experiments when filtering by visible tracked property names', function (): void {
    config()->set('growth.features.owner.include_global', true);
    config()->set('signals.owner.include_global', false);

    $owner = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000008d');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    filamentGrowth_createExperimentForOwner($owner, 'Owned Search Experiment', 'O');
    [$globalExperiment] = filamentGrowth_createGlobalExperiment('Global Search Experiment', 'G');
    $method = new ReflectionMethod(ExperimentResource::class, 'filterByTrackedPropertyName');
    $method->setAccessible(true);

    $matchingNames = $method->invoke(
        null,
        ExperimentResource::getEloquentQuery(),
        'Global Search Experiment Property',
    )->pluck('name')->all();

    expect($matchingNames)->toContain((string) $globalExperiment->name)
        ->and($matchingNames)->not->toContain('Owned Search Experiment');
});

it('prunes stale variant settings when the selected experiment module changes', function (): void {
    $owner = filamentGrowth_makeOwner('00000000-0000-0000-0000-00000000007e');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    [$salesExperiment] = filamentGrowth_createExperimentForOwner($owner, 'Sales Settings Experiment', 'S');
    [$pricingExperiment] = filamentGrowth_createExperimentForOwner($owner, 'Pricing Settings Experiment', 'P');

    OwnerContext::withOwner($owner, function () use ($pricingExperiment, $salesExperiment): void {
        $salesExperiment->module_type = 'sales_page_test';
        $salesExperiment->save();

        $pricingExperiment->module_type = 'pricing_test';
        $pricingExperiment->save();
    });

    $data = VariantResource::normalizeFormData([
        'experiment_id' => (string) $pricingExperiment->getKey(),
        'settings' => [
            'headline' => 'Keep me out',
            'cta_copy' => 'Nope',
            'price_label' => 'Starter',
            'price_minor' => 12345,
            'currency' => 'USD',
        ],
    ]);

    expect($data['settings'])->toBe([
        'price_label' => 'Starter',
        'price_minor' => 12345,
        'currency' => 'USD',
    ]);
});
