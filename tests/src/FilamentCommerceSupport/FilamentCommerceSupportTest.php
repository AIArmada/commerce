<?php

declare(strict_types=1);

use AIArmada\FilamentCommerceSupport\FilamentCommerceSupportPlugin;
use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;

beforeEach(function (): void {
    config()->set('filament-commerce-support.navigation.enabled', true);
    config()->set('commerce-support.filament.navigation', [
        'enabled' => true,
        'groups' => [],
        'packages' => [],
        'items' => [],
    ]);
});

it('has correct plugin id', function (): void {
    $plugin = FilamentCommerceSupportPlugin::make();

    expect($plugin->getId())->toBe('filament-commerce-support');
});

it('returns the settings group name', function (): void {
    expect(CommerceNavigationSettings::group())->toBe('commerce-navigation');
});

it('merges groups into commerce-support config via navigation configurator', function (): void {
    config()->set('commerce-support.filament.navigation.groups', [
        'Catalog' => ['label' => 'Catalog', 'sort' => 10],
    ]);

    $settings = mock(CommerceNavigationSettings::class, function ($mock): void {
        $mock->groups = ['Operations' => ['label' => 'Operations', 'sort' => 20]];
        $mock->overrides = [];
    });
    app()->instance(CommerceNavigationSettings::class, $settings);

    NavigationConfigurator::apply();

    $groups = config('commerce-support.filament.navigation.groups');

    expect($groups)->toHaveKeys(['Catalog', 'Operations'])
        ->and($groups['Operations']['label'])->toBe('Operations');
});

it('merges overrides into commerce-support config via navigation configurator', function (): void {
    config()->set('commerce-support.filament.navigation.items', [
        'AIArmada\FilamentProducts\Resources\ProductResource' => [
            'group' => 'Catalog',
            'sort' => 5,
        ],
    ]);

    $settings = mock(CommerceNavigationSettings::class, function ($mock): void {
        $mock->groups = [];
        $mock->overrides = [
            'AIArmada\FilamentOrders\Resources\OrderResource' => [
                'hidden' => true,
            ],
        ];
    });
    app()->instance(CommerceNavigationSettings::class, $settings);

    NavigationConfigurator::apply();

    $items = config('commerce-support.filament.navigation.items');

    expect($items)->toHaveKeys([
        'AIArmada\FilamentProducts\Resources\ProductResource',
        'AIArmada\FilamentOrders\Resources\OrderResource',
    ])->and($items['AIArmada\FilamentOrders\Resources\OrderResource']['hidden'])->toBeTrue();
});

it('does not merge when navigation is disabled', function (): void {
    config()->set('filament-commerce-support.navigation.enabled', false);

    $settings = mock(CommerceNavigationSettings::class, function ($mock): void {
        $mock->groups = ['Test' => ['label' => 'Test']];
        $mock->overrides = [];
    });
    app()->instance(CommerceNavigationSettings::class, $settings);

    NavigationConfigurator::apply();

    expect(config('commerce-support.filament.navigation.groups'))->toBe([]);
});

it('merges settings groups without overriding existing code config', function (): void {
    config()->set('commerce-support.filament.navigation.groups', [
        'Catalog' => ['label' => 'Catalog', 'sort' => 10],
    ]);

    $settings = mock(CommerceNavigationSettings::class, function ($mock): void {
        $mock->groups = ['Operations' => ['label' => 'Operations', 'sort' => 20]];
        $mock->overrides = [];
    });
    app()->instance(CommerceNavigationSettings::class, $settings);

    NavigationConfigurator::apply();

    expect(config('commerce-support.filament.navigation.groups.Catalog'))->toBe([
        'label' => 'Catalog',
        'sort' => 10,
    ]);
});
