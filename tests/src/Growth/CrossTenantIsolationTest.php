<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

function growthIsolationOwner(string $label): User
{
    return User::query()->create([
        'name' => 'Isolation Owner ' . $label,
        'email' => 'isolation-' . Str::lower($label) . '-' . Str::lower(Str::random(6)) . '@example.com',
        'password' => 'secret',
    ]);
}

function growthIsolationExperiment(User $owner, string $name): Experiment
{
    return OwnerContext::withOwner($owner, function () use ($name): Experiment {
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

        return $experiment;
    });
}

function growthIsolationGlobalExperiment(string $name): Experiment
{
    return OwnerContext::withOwner(null, function () use ($name): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => $name . ' Global Property',
            'slug' => Str::slug($name) . '-global-' . Str::lower(Str::random(6)),
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
            'slug' => Str::slug($name) . '-global-' . Str::lower(Str::random(6)),
        ]);

        return $experiment;
    });
}

it('does not return experiments across tenant boundaries', function (): void {
    $ownerA = growthIsolationOwner('A');
    $ownerB = growthIsolationOwner('B');

    $experimentA = growthIsolationExperiment($ownerA, 'Owner A Experiment');
    $experimentB = growthIsolationExperiment($ownerB, 'Owner B Experiment');

    $ownerAIds = OwnerContext::withOwner($ownerA, fn (): array => Experiment::query()->pluck('id')->all());
    $ownerBIds = OwnerContext::withOwner($ownerB, fn (): array => Experiment::query()->pluck('id')->all());

    expect($ownerAIds)->toContain($experimentA->getKey())
        ->not->toContain($experimentB->getKey())
        ->and($ownerBIds)->toContain($experimentB->getKey())
        ->not->toContain($experimentA->getKey());
});

it('prevents cross-tenant variant creation against another owners experiment', function (): void {
    $ownerA = growthIsolationOwner('A2');
    $ownerB = growthIsolationOwner('B2');
    $experimentA = growthIsolationExperiment($ownerA, 'Cross Tenant Experiment');

    expect(fn () => OwnerContext::withOwner($ownerB, fn (): Variant => Variant::query()->create([
        'experiment_id' => $experimentA->getKey(),
        'code' => 'BAD',
        'name' => 'Cross Tenant Variant',
        'traffic_percentage' => 50,
        'position' => 1,
        'is_control' => false,
        'is_active' => true,
    ])))->toThrow(AuthorizationException::class);
});

it('prevents tenant-scoped variant creation against a global experiment when include_global only enables reads', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $owner = growthIsolationOwner('GLOBAL');
    $globalExperiment = growthIsolationGlobalExperiment('Global Experiment');

    expect(fn () => OwnerContext::withOwner($owner, fn (): Variant => Variant::query()->create([
        'experiment_id' => $globalExperiment->getKey(),
        'code' => 'GLB',
        'name' => 'Global Variant Attempt',
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => false,
        'is_active' => true,
    ])))->toThrow(InvalidArgumentException::class, 'Variant owner must match the parent experiment owner.');
});
