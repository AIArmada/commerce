<?php

declare(strict_types=1);

use AIArmada\Filament\Communications\FilamentCommunicationsPlugin;
use AIArmada\Filament\Communications\Resources\CommunicationBatchResource;
use AIArmada\Filament\Communications\Resources\CommunicationDeliveryResource;
use AIArmada\Filament\Communications\Resources\CommunicationPreferenceResource;
use AIArmada\Filament\Communications\Resources\CommunicationResource;
use AIArmada\Filament\Communications\Resources\CommunicationSuppressionResource;
use AIArmada\Filament\Communications\Resources\CommunicationTemplateResource;
use AIArmada\Filament\Communications\Resources\CommunicationThreadResource;
use AIArmada\Filament\Communications\Widgets\DeliveryStatusOverviewWidget;

$resources = [
    CommunicationResource::class,
    CommunicationDeliveryResource::class,
    CommunicationThreadResource::class,
    CommunicationTemplateResource::class,
    CommunicationPreferenceResource::class,
    CommunicationSuppressionResource::class,
    CommunicationBatchResource::class,
];

describe('navigation configuration', function () use ($resources): void {
    beforeEach(function (): void {
        config()->set('filament-communications.navigation.group', 'Test Communications');
        config()->set('filament-communications.navigation.sort', 80);
    });

    test('getNavigationGroup returns config value for all resources', function (string $resourceClass): void {
        expect($resourceClass::getNavigationGroup())->toBe('Test Communications');
    })->with($resources);

    test('getNavigationSort returns config value for all resources', function (string $resourceClass): void {
        expect($resourceClass::getNavigationSort())->toBe(80);
    })->with($resources);

    test('getNavigationGroup is not null for all resources', function (string $resourceClass): void {
        expect($resourceClass::getNavigationGroup())->not->toBeNull();
    })->with($resources);
});

describe('resource methods', function () use ($resources): void {
    test('getEloquentQuery method exists on all resources', function (string $resourceClass): void {
        expect(method_exists($resourceClass, 'getEloquentQuery'))->toBeTrue();
    })->with($resources);

    test('getPages returns array with index and view for all resources', function (string $resourceClass): void {
        $pages = $resourceClass::getPages();

        expect($pages)->toBeArray();
        expect($pages)->toHaveKey('index');
        expect($pages)->toHaveKey('view');
    })->with($resources);
});

test('no resource declares static $navigationGroup', function () use ($resources): void {
    foreach ($resources as $resourceClass) {
        $reflection = new ReflectionClass($resourceClass);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            if ($property->getName() === 'navigationGroup') {
                expect($property->getDeclaringClass()->getName())->not->toBe($resourceClass);
            }
        }
    }
});

test('FilamentCommunicationsPlugin can be instantiated', function (): void {
    $plugin = FilamentCommunicationsPlugin::make();

    expect($plugin)->toBeInstanceOf(FilamentCommunicationsPlugin::class);
    expect($plugin->getId())->toBe('filament-communications');
});

test('DeliveryStatusOverviewWidget can be instantiated', function (): void {
    $widget = app(DeliveryStatusOverviewWidget::class);

    expect($widget)->toBeInstanceOf(DeliveryStatusOverviewWidget::class);
    expect(method_exists($widget, 'getStats'))->toBeTrue();
});
