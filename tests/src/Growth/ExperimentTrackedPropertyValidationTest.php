<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
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
    return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Tracked Property ' . $label,
        'slug' => 'tracked-property-' . Str::slug($label) . '-' . Str::lower(Str::random(6)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));
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
    ])))->toThrow(\RuntimeException::class, 'Invalid tracked_property_id: does not belong to the current owner scope.');

    expect(fn (): Experiment => OwnerContext::withOwner($ownerA, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $propertyA->getKey(),
        'name' => 'Owner A Experiment',
        'slug' => 'owner-a-experiment',
    ])))->not->toThrow(\RuntimeException::class);
});