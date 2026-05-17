<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function growthTrackedPropertyOwner(string $label): User
{
    return User::query()->create([
        'name' => 'Growth Property Owner ' . $label,
        'email' => 'growth-property-owner-' . Str::lower($label) . '-' . Str::lower(Str::random(6)) . '@example.com',
        'password' => 'secret',
    ]);
}

function growthOwnedTrackedProperty(User $owner, string $label): TrackedProperty
{
    return OwnerContext::withOwner($owner, function () use ($label, $owner): TrackedProperty {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Tracked Property ' . $label,
            'slug' => 'tracked-property-' . Str::slug($label) . '-' . Str::lower(Str::random(6)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        if (! config('signals.owner.enabled', true)) {
            $trackedProperty->owner_type = $owner->getMorphClass();
            $trackedProperty->owner_id = (string) $owner->getKey();
            $trackedProperty->save();
        }

        return $trackedProperty;
    });
}

it('rejects saving an experiment with a tracked property from another owner scope', function (): void {
    $ownerA = growthTrackedPropertyOwner('A');
    $ownerB = growthTrackedPropertyOwner('B');

    $propertyA = growthOwnedTrackedProperty($ownerA, 'Owner A');
    $propertyB = growthOwnedTrackedProperty($ownerB, 'Owner B');

    expect(fn (): Experiment => OwnerContext::withOwner($ownerA, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyB->getKey(),
        'name' => 'Cross Tenant Experiment',
        'slug' => 'cross-tenant-experiment',
    ])))->toThrow(RuntimeException::class, 'Invalid tracked_property_id: does not belong to the current owner scope.');

    expect(fn (): Experiment => OwnerContext::withOwner($ownerA, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyA->getKey(),
        'name' => 'Owner A Experiment',
        'slug' => 'owner-a-experiment',
    ])))->not->toThrow(RuntimeException::class);
});

it('rejects saving an experiment with a tracked property from another owner scope when signals owner scoping is disabled', function (): void {
    config()->set('signals.owner.enabled', false);

    $ownerA = growthTrackedPropertyOwner('Signals Disabled A');
    $ownerB = growthTrackedPropertyOwner('Signals Disabled B');

    $propertyA = growthOwnedTrackedProperty($ownerA, 'Signals Disabled Owner A');
    $propertyB = growthOwnedTrackedProperty($ownerB, 'Signals Disabled Owner B');

    expect(fn (): Experiment => OwnerContext::withOwner($ownerA, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyB->getKey(),
        'name' => 'Signals Disabled Cross Tenant Experiment',
        'slug' => 'signals-disabled-cross-tenant-experiment',
    ])))->toThrow(RuntimeException::class, 'Invalid tracked_property_id: does not belong to the current owner scope.');

    expect(fn (): Experiment => OwnerContext::withOwner($ownerA, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyA->getKey(),
        'name' => 'Signals Disabled Owner A Experiment',
        'slug' => 'signals-disabled-owner-a-experiment',
    ])))->not->toThrow(RuntimeException::class);
});

it('rejects saving an experiment with a missing tracked property when owner scoping is disabled', function (): void {
    config()->set('growth.features.owner.enabled', false);
    config()->set('signals.owner.enabled', false);

    $owner = growthTrackedPropertyOwner('Disabled');

    expect(fn (): Experiment => OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => (string) Str::uuid(),
        'name' => 'Missing Property Experiment',
        'slug' => 'missing-property-experiment',
    ])))->toThrow(RuntimeException::class, 'Invalid tracked_property_id: tracked property does not exist.');
});

it('rejects saving an experiment with a foreign tracked property when growth owner scoping is disabled but signals owner scoping remains enabled', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = growthTrackedPropertyOwner('Signals Enabled A');
    $ownerB = growthTrackedPropertyOwner('Signals Enabled B');

    $propertyA = growthOwnedTrackedProperty($ownerA, 'Signals Enabled Owner A');
    $propertyB = growthOwnedTrackedProperty($ownerB, 'Signals Enabled Owner B');

    expect(fn (): Experiment => OwnerContext::withOwner($ownerA, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyB->getKey(),
        'name' => 'Signals Enabled Cross Tenant Experiment',
        'slug' => 'signals-enabled-cross-tenant-experiment',
    ])))->toThrow(RuntimeException::class, 'Invalid tracked_property_id: does not belong to the current owner scope.');

    expect(fn (): Experiment => OwnerContext::withOwner($ownerA, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyA->getKey(),
        'name' => 'Signals Enabled Owner A Experiment',
        'slug' => 'signals-enabled-owner-a-experiment',
    ])))->not->toThrow(RuntimeException::class);
});

it('rejects changing the tracked property on a persisted experiment', function (): void {
    $owner = growthTrackedPropertyOwner('Immutable Experiment');
    $propertyA = growthOwnedTrackedProperty($owner, 'Immutable Experiment A');
    $propertyB = growthOwnedTrackedProperty($owner, 'Immutable Experiment B');

    $experiment = OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyA->getKey(),
        'name' => 'Immutable Tracked Property Experiment',
        'slug' => 'immutable-tracked-property-experiment',
    ]));

    expect(fn (): bool => OwnerContext::withOwner($owner, function () use ($experiment, $propertyB): bool {
        $experiment->tracked_property_id = (string) $propertyB->getKey();

        return $experiment->save();
    }))->toThrow(InvalidArgumentException::class, 'Growth experiment tracked_property_id cannot be changed after creation.');
});

it('rejects updating a persisted experiment after its tracked property drifts out of the current owner scope', function (): void {
    $ownerA = growthTrackedPropertyOwner('Drifted Experiment A');
    $ownerB = growthTrackedPropertyOwner('Drifted Experiment B');
    $propertyA = growthOwnedTrackedProperty($ownerA, 'Drifted Experiment Property A');
    $propertyB = growthOwnedTrackedProperty($ownerB, 'Drifted Experiment Property B');

    $experiment = OwnerContext::withOwner($ownerA, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyA->getKey(),
        'name' => 'Drifted Experiment',
        'slug' => 'drifted-experiment',
    ]));

    DB::table($experiment->getTable())
        ->where('id', $experiment->getKey())
        ->update(['tracked_property_id' => $propertyB->getKey()]);

    expect(fn (): bool => OwnerContext::withOwner($ownerA, function () use ($experiment): bool {
        $corruptExperiment = Experiment::query()->findOrFail($experiment->getKey());
        $corruptExperiment->name = 'Retitled Drifted Experiment';

        return $corruptExperiment->save();
    }))->toThrow(RuntimeException::class, 'Invalid tracked_property_id: does not belong to the current owner scope.');
});

it('persists an owner-context uniqueness scope for ownerless experiments when growth owner scoping is disabled', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $owner = growthTrackedPropertyOwner('Global Scope');
    $trackedProperty = growthOwnedTrackedProperty($owner, 'Global Scope');

    $experiment = OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'name' => 'Ownerless Experiment',
        'slug' => 'ownerless-experiment',
        'owner_type' => null,
        'owner_id' => null,
    ]));

    expect($experiment->getRawOriginal('owner_scope'))->toBe(OwnerScopeKey::forOwner($owner))
        ->and($experiment->owner_type)->toBeNull()
        ->and($experiment->owner_id)->toBeNull();
});

it('allows duplicate experiment slugs across different tracked property owners when growth owner scoping is disabled', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = growthTrackedPropertyOwner('Duplicate Slug A');
    $ownerB = growthTrackedPropertyOwner('Duplicate Slug B');
    $propertyA = growthOwnedTrackedProperty($ownerA, 'Duplicate Slug Property A');
    $propertyB = growthOwnedTrackedProperty($ownerB, 'Duplicate Slug Property B');

    $experimentA = OwnerContext::withOwner($ownerA, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyA->getKey(),
        'name' => 'Shared Slug Experiment A',
        'slug' => 'shared-growth-slug',
        'owner_type' => null,
        'owner_id' => null,
    ]));

    $experimentB = OwnerContext::withOwner($ownerB, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyB->getKey(),
        'name' => 'Shared Slug Experiment B',
        'slug' => 'shared-growth-slug',
        'owner_type' => null,
        'owner_id' => null,
    ]));

    expect($experimentA->slug)->toBe('shared-growth-slug')
        ->and($experimentB->slug)->toBe('shared-growth-slug')
        ->and($experimentA->getRawOriginal('owner_scope'))->toBe(OwnerScopeKey::forOwner($ownerA))
        ->and($experimentB->getRawOriginal('owner_scope'))->toBe(OwnerScopeKey::forOwner($ownerB));
});
