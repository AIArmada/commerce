<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalSegment;
use Illuminate\Database\QueryException;

uses(SignalsTestCase::class);

it('rejects duplicate global signal slugs while accepting explicit global context', function (): void {
    OwnerContext::withOwner(null, function (): void {
        SignalSegment::query()->create(['name' => 'Global One', 'slug' => 'global-segment']);
        expect(fn () => SignalSegment::query()->create([
            'name' => 'Global Two', 'slug' => 'global-segment',
        ]))->toThrow(QueryException::class);

        $goal = SignalGoal::query()->create([
            'name' => 'Global Goal', 'slug' => 'global-goal', 'event_name' => 'checkout.completed',
        ]);
        expect($goal->owner_scope)->toBe(OwnerScopeKey::GLOBAL);
    });
});

it('isolates equal slugs by owner and auto assigns the canonical scope key', function (): void {
    $ownerA = User::query()->create(['name' => 'Signal Owner A', 'email' => 'signal-owner-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Signal Owner B', 'email' => 'signal-owner-b@example.com', 'password' => 'secret']);

    $segmentA = OwnerContext::withOwner($ownerA, fn (): SignalSegment => SignalSegment::query()->create([
        'name' => 'Owner A Segment', 'slug' => 'same-segment',
    ]));
    $segmentB = OwnerContext::withOwner($ownerB, fn (): SignalSegment => SignalSegment::query()->create([
        'name' => 'Owner B Segment', 'slug' => 'same-segment',
    ]));

    expect($segmentA->owner_scope)->toBe(OwnerScopeKey::forOwner($ownerA))
        ->and($segmentB->owner_scope)->toBe(OwnerScopeKey::forOwner($ownerB));
});
