<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentSignals\Resources\TrackedPropertyResource;
use AIArmada\Signals\Models\TrackedProperty;

uses(FilamentSignalsTestCase::class);

it('scopes tracked properties to the current owner', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Filament Owner A',
        'email' => 'filament-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Filament Owner B',
        'email' => 'filament-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Property',
        'slug' => 'owner-a-property',
        'write_key' => 'owner-a-write-key',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Property',
        'slug' => 'owner-b-property',
        'write_key' => 'owner-b-write-key',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $query = TrackedPropertyResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()?->id)->toBe($propertyA->id);
});
