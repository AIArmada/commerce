<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkCollectionResource;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\BookmarkResource;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\FollowResource;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\ReactionResource;
use AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Resources\ResponseResource;
use AIArmada\FilamentEngagement\Features\Overview\EntryPoints\Filament\Widgets\EngagementOverviewWidget;
use AIArmada\FilamentEngagement\Features\Reminders\EntryPoints\Filament\Resources\ReminderResource;
use AIArmada\FilamentEngagement\Features\Subscriptions\EntryPoints\Filament\Resources\SubscriptionResource;
use AIArmada\FilamentEngagement\FilamentEngagementPlugin;
use Filament\Panel;

it('exposes the plugin resources and widget', function (): void {
    $plugin = FilamentEngagementPlugin::make();

    $panel = Panel::make()->id('engagement-test');
    $plugin->register($panel);

    $resources = $panel->getResources();
    $widgets = $panel->getWidgets();

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
