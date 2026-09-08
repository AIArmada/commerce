<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Models\Follow;
use AIArmada\FilamentEngagement\Actions\BookmarkAction;
use AIArmada\FilamentEngagement\Actions\FollowAction;
use AIArmada\FilamentEngagement\Actions\ReactAction;
use AIArmada\FilamentEngagement\Actions\RemoveBookmarkAction;
use AIArmada\FilamentEngagement\Actions\RespondAction;
use AIArmada\FilamentEngagement\Actions\SetReminderAction;
use AIArmada\FilamentEngagement\Actions\SubscribeAction;
use AIArmada\FilamentEngagement\Actions\UnfollowAction;
use AIArmada\FilamentEngagement\Widgets\EngagementOverviewWidget;
use Illuminate\Auth\Access\AuthorizationException;

it('revalidates owner access before every reusable action calls a manager', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Action Owner A',
        'email' => 'engagement-action-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Action Owner B',
        'email' => 'engagement-action-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $record = OwnerContext::withOwner($ownerB, function () use ($ownerB): Follow {
        return Follow::query()->create([
            'follower_type' => $ownerB->getMorphClass(),
            'follower_id' => $ownerB->getKey(),
            'followable_type' => $ownerB->getMorphClass(),
            'followable_id' => $ownerB->getKey(),
            'status' => FollowStatus::Active,
        ]);
    });

    $actions = [
        BookmarkAction::class,
        FollowAction::class,
        ReactAction::class,
        RemoveBookmarkAction::class,
        RespondAction::class,
        SetReminderAction::class,
        SubscribeAction::class,
        UnfollowAction::class,
    ];

    OwnerContext::withOwner($ownerA, function () use ($actions, $record): void {
        foreach ($actions as $actionClass) {
            $callback = $actionClass::make()->getActionFunction();

            expect($callback)->not->toBeNull();

            $arguments = in_array($actionClass, [
                ReactAction::class,
                RespondAction::class,
                SetReminderAction::class,
            ], true) ? [[], $record] : [null, $record];

            expect(fn (): mixed => $callback(...$arguments))
                ->toThrow(AuthorizationException::class);
        }
    });
});

it('keeps the overview widget cache isolated per owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Widget Owner A',
        'email' => 'engagement-widget-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Widget Owner B',
        'email' => 'engagement-widget-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        Follow::query()->create([
            'follower_type' => $ownerA->getMorphClass(),
            'follower_id' => $ownerA->getKey(),
            'followable_type' => $ownerA->getMorphClass(),
            'followable_id' => $ownerA->getKey(),
            'status' => FollowStatus::Active,
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($ownerB): void {
        foreach (range(1, 2) as $_index) {
            Follow::query()->create([
                'follower_type' => $ownerB->getMorphClass(),
                'follower_id' => $ownerB->getKey(),
                'followable_type' => $ownerB->getMorphClass(),
                'followable_id' => $ownerB->getKey(),
                'status' => FollowStatus::Active,
            ]);
        }
    });

    $getStats = new ReflectionMethod(EngagementOverviewWidget::class, 'getStats');

    $ownerAStats = OwnerContext::withOwner($ownerA, fn (): array => $getStats->invoke(new EngagementOverviewWidget));
    $ownerBStats = OwnerContext::withOwner($ownerB, fn (): array => $getStats->invoke(new EngagementOverviewWidget));

    expect($ownerAStats[0]->getValue())->toBe(1)
        ->and($ownerBStats[0]->getValue())->toBe(2);
});
