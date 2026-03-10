<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentSignals\Resources\SignalGoalResource;
use AIArmada\Signals\Models\SignalGoal;

uses(FilamentSignalsTestCase::class);

it('scopes signal goals to the current owner', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Goal Resource Owner A',
        'email' => 'goal-resource-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Goal Resource Owner B',
        'email' => 'goal-resource-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $goalA = SignalGoal::query()->create([
        'name' => 'Owner A Goal',
        'slug' => 'owner-a-goal',
        'goal_type' => 'conversion',
        'event_name' => 'order.paid',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $goalB = SignalGoal::query()->create([
        'name' => 'Owner B Goal',
        'slug' => 'owner-b-goal',
        'goal_type' => 'engagement',
        'event_name' => 'newsletter.subscribed',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $query = SignalGoalResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()?->id)->toBe($goalA->id)
        ->and($goalB->fresh()?->id)->not()->toBe($goalA->id);
});
