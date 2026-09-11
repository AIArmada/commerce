<?php

declare(strict_types=1);

use AIArmada\FilamentCommerceSupport\FilamentCommerceSupportServiceProvider;
use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;
use Spatie\LaravelSettings\Events\SettingsSaved;

it('restores genuine navigation defaults when settings are saved mid-worker', function (): void {
    $captured = new ReflectionProperty(NavigationConfigurator::class, 'captured');
    $originalGroups = new ReflectionProperty(NavigationConfigurator::class, 'originalGroupConfig');
    $originalItems = new ReflectionProperty(NavigationConfigurator::class, 'originalItemsConfig');
    $captured->setValue(null, false);
    $originalGroups->setValue(null, []);
    $originalItems->setValue(null, []);

    $baseGroups = [
        'Catalog' => ['label' => 'Catalog', 'sort' => 10],
    ];
    $baseItems = [
        'catalog.products' => ['group' => 'Catalog', 'sort' => 10],
    ];
    config()->set('commerce-support.filament.navigation.groups', $baseGroups);
    config()->set('commerce-support.filament.navigation.items', $baseItems);
    config()->set('filament-commerce-support.navigation.enabled', true);

    $settings = mock(CommerceNavigationSettings::class, function ($mock): void {
        $mock->groups = ['Operations' => ['label' => 'Operations', 'sort' => 20]];
        $mock->overrides = ['catalog.products' => ['hidden' => true]];
    });
    app()->instance(CommerceNavigationSettings::class, $settings);

    (new FilamentCommerceSupportServiceProvider(app()))->packageRegistered();
    NavigationConfigurator::apply();

    $items = config('commerce-support.filament.navigation.items');

    expect(config('commerce-support.filament.navigation.groups'))->toHaveKey('Operations')
        ->and($items['catalog.products']['hidden'])->toBeTrue();

    $settings->groups = [];
    $settings->overrides = [];
    event(new SettingsSaved($settings));
    NavigationConfigurator::apply();

    expect(config('commerce-support.filament.navigation.groups'))->toBe($baseGroups)
        ->and(config('commerce-support.filament.navigation.items'))->toBe($baseItems)
        ->and($captured->isPrivate())->toBeTrue()
        ->and($originalGroups->isPrivate())->toBeTrue()
        ->and($originalItems->isPrivate())->toBeTrue();
});
