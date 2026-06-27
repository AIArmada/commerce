<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Models\Follow;
use AIArmada\FilamentEngagement\FilamentEngagementPlugin;
use AIArmada\FilamentEngagement\FilamentEngagementServiceProvider;
use AIArmada\FilamentEngagement\Resources\BookmarkCollectionResource;
use AIArmada\FilamentEngagement\Resources\BookmarkResource;
use AIArmada\FilamentEngagement\Resources\FollowResource;
use AIArmada\FilamentEngagement\Resources\ReactionResource;
use AIArmada\FilamentEngagement\Resources\ReminderResource;
use AIArmada\FilamentEngagement\Resources\ResponseResource;
use AIArmada\FilamentEngagement\Resources\SubscriptionResource;
use AIArmada\FilamentEngagement\Widgets\EngagementOverviewWidget;

it('exposes the plugin resources and widget', function (): void {
    $plugin = FilamentEngagementPlugin::make();

    $resources = (new ReflectionMethod($plugin, 'getResources'))->invoke($plugin);
    $widgets = (new ReflectionMethod($plugin, 'getWidgets'))->invoke($plugin);

    expect($plugin->getId())->toBe('filament-engagement')
        ->and($resources)->toContain(
            FollowResource::class,
            BookmarkResource::class,
            BookmarkCollectionResource::class,
            ResponseResource::class,
            ReactionResource::class,
            SubscriptionResource::class,
            ReminderResource::class,
        )
        ->and($widgets)->toContain(EngagementOverviewWidget::class);
});

it('loads the filament engagement package config and plugin singleton', function (): void {
    app()->register(FilamentEngagementServiceProvider::class);

    expect(config('filament-engagement.navigation.group'))->toBe('Engagement')
        ->and(app(FilamentEngagementPlugin::class))->toBeInstanceOf(FilamentEngagementPlugin::class);
});

it('scopes engagement resources to the current owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Filament Engagement Owner A',
        'email' => 'filament-engagement-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Filament Engagement Owner B',
        'email' => 'filament-engagement-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    foreach ([$ownerA, $ownerB] as $owner) {
        OwnerContext::withOwner($owner, fn () => Follow::query()->create([
            'follower_type' => $owner->getMorphClass(),
            'follower_id' => $owner->id,
            'followable_type' => $owner->getMorphClass(),
            'followable_id' => $owner->id,
            'status' => FollowStatus::Active,
        ]));
    }

    $count = OwnerContext::withOwner(
        $ownerA,
        fn (): int => FollowResource::getEloquentQuery()->count(),
    );

    expect($count)->toBe(1);
});
