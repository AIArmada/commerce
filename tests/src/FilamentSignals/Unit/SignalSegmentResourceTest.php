<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentSignals\Resources\SignalSegmentResource;
use AIArmada\Signals\Models\SignalSegment;

uses(FilamentSignalsTestCase::class);

it('scopes signal segments to the current owner', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Segment Resource Owner A',
        'email' => 'segment-resource-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Segment Resource Owner B',
        'email' => 'segment-resource-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $segmentA = SignalSegment::query()->create([
        'name' => 'Owner A Segment',
        'slug' => 'owner-a-segment',
    ]);
    $segmentA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $segmentB = SignalSegment::query()->create([
        'name' => 'Owner B Segment',
        'slug' => 'owner-b-segment',
    ]);
    $segmentB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $query = SignalSegmentResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()?->id)->toBe($segmentA->id);
});
