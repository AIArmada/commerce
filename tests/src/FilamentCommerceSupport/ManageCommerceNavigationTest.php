<?php

declare(strict_types=1);

use AIArmada\FilamentCommerceSupport\Pages\ManageCommerceNavigation;

it('resolves settings form groups through the canonical navigation engine', function (): void {
    config()->set('commerce-support.filament.navigation.groups', [
        'Catalog' => ['label' => 'Catalog', 'sort' => 10],
    ]);
    config()->set('filament-commerce-support.navigation.enabled', true);

    $page = new ManageCommerceNavigation('navigation-engine-test');
    $method = new ReflectionMethod(ManageCommerceNavigation::class, 'getDefaultGroups');

    $groups = $method->invoke($page);

    expect($groups)->toHaveKey('Catalog')
        ->and($groups['Catalog']['label'])->toBe('Catalog')
        ->and($groups['Catalog']['sort'])->toBe(10)
        ->and($groups['Catalog']['hidden'])->toBeFalse();
});
