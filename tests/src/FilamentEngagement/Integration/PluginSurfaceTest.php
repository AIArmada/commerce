<?php

declare(strict_types=1);

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
