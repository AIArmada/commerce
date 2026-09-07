<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressSnapshot;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource;

it('scopes address and snapshot resources to the current owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A',
        'country_code' => 'MY',
    ]));
    $addressB = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Owner B',
        'country_code' => 'MY',
    ]));

    $snapshotA = OwnerContext::withOwner($ownerA, fn (): AddressSnapshot => AddressSnapshot::query()->create([
        'snapshotable_type' => $ownerA->getMorphClass(),
        'snapshotable_id' => $ownerA->getKey(),
        'line1' => 'Owner A snapshot',
    ]));
    $snapshotB = OwnerContext::withOwner($ownerB, fn (): AddressSnapshot => AddressSnapshot::query()->create([
        'snapshotable_type' => $ownerB->getMorphClass(),
        'snapshotable_id' => $ownerB->getKey(),
        'line1' => 'Owner B snapshot',
    ]));

    expect(OwnerContext::withOwner($ownerA, fn (): array => AddressResource::getEloquentQuery()->pluck('id')->all()))
        ->toEqual([$addressA->id])
        ->and(OwnerContext::withOwner($ownerB, fn (): array => AddressResource::getEloquentQuery()->pluck('id')->all()))
        ->toEqual([$addressB->id])
        ->and(OwnerContext::withOwner($ownerA, fn (): array => AddressSnapshotResource::getEloquentQuery()->pluck('id')->all()))
        ->toEqual([$snapshotA->id])
        ->and(OwnerContext::withOwner($ownerB, fn (): array => AddressSnapshotResource::getEloquentQuery()->pluck('id')->all()))
        ->toEqual([$snapshotB->id]);
});
