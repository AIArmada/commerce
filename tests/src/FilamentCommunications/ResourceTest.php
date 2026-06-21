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

test('CommunicationResource navigation group reads from config', function (): void {
    expect(CommunicationResource::getNavigationGroup())->toBe(config('filament-communications.navigation.group'));
});

test('CommunicationResource navigation sort reads from config', function (): void {
    expect(CommunicationResource::getNavigationSort())->toBe(config('filament-communications.navigation.sort'));
});

test('CommunicationDeliveryResource navigation group reads from config', function (): void {
    expect(CommunicationDeliveryResource::getNavigationGroup())->toBe(config('filament-communications.navigation.group'));
});

test('CommunicationDeliveryResource navigation sort reads from config', function (): void {
    expect(CommunicationDeliveryResource::getNavigationSort())->toBe(config('filament-communications.navigation.sort'));
});

test('CommunicationThreadResource navigation group reads from config', function (): void {
    expect(CommunicationThreadResource::getNavigationGroup())->toBe(config('filament-communications.navigation.group'));
});

test('CommunicationThreadResource navigation sort reads from config', function (): void {
    expect(CommunicationThreadResource::getNavigationSort())->toBe(config('filament-communications.navigation.sort'));
});

test('CommunicationTemplateResource navigation group reads from config', function (): void {
    expect(CommunicationTemplateResource::getNavigationGroup())->toBe(config('filament-communications.navigation.group'));
});

test('CommunicationTemplateResource navigation sort reads from config', function (): void {
    expect(CommunicationTemplateResource::getNavigationSort())->toBe(config('filament-communications.navigation.sort'));
});

test('CommunicationPreferenceResource navigation group reads from config', function (): void {
    expect(CommunicationPreferenceResource::getNavigationGroup())->toBe(config('filament-communications.navigation.group'));
});

test('CommunicationPreferenceResource navigation sort reads from config', function (): void {
    expect(CommunicationPreferenceResource::getNavigationSort())->toBe(config('filament-communications.navigation.sort'));
});

test('CommunicationSuppressionResource navigation group reads from config', function (): void {
    expect(CommunicationSuppressionResource::getNavigationGroup())->toBe(config('filament-communications.navigation.group'));
});

test('CommunicationSuppressionResource navigation sort reads from config', function (): void {
    expect(CommunicationSuppressionResource::getNavigationSort())->toBe(config('filament-communications.navigation.sort'));
});

test('CommunicationBatchResource navigation group reads from config', function (): void {
    expect(CommunicationBatchResource::getNavigationGroup())->toBe(config('filament-communications.navigation.group'));
});

test('CommunicationBatchResource navigation sort reads from config', function (): void {
    expect(CommunicationBatchResource::getNavigationSort())->toBe(config('filament-communications.navigation.sort'));
});

test('every resource has required static methods', function (string $resource): void {
    expect(method_exists($resource, 'getEloquentQuery'))->toBeTrue();
    expect(method_exists($resource, 'getPages'))->toBeTrue();
    expect(method_exists($resource, 'getNavigationGroup'))->toBeTrue();
})->with([
    CommunicationResource::class,
    CommunicationDeliveryResource::class,
    CommunicationThreadResource::class,
    CommunicationTemplateResource::class,
    CommunicationPreferenceResource::class,
    CommunicationSuppressionResource::class,
    CommunicationBatchResource::class,
]);

test('FilamentCommunicationsPlugin can be instantiated', function (): void {
    $plugin = FilamentCommunicationsPlugin::make();
    expect($plugin)->toBeInstanceOf(FilamentCommunicationsPlugin::class);
    expect($plugin->getId())->toBe('filament-communications');
});

test('DeliveryStatusOverviewWidget exists', function (): void {
    expect(class_exists(DeliveryStatusOverviewWidget::class))->toBeTrue();
});
