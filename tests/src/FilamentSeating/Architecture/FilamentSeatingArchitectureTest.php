<?php

declare(strict_types=1);

use AIArmada\FilamentSeating\FilamentSeatingPlugin;
use AIArmada\FilamentSeating\FilamentSeatingServiceProvider;
use AIArmada\FilamentSeating\Resources\SeatMapResource;
use Spatie\LaravelPackageTools\PackageServiceProvider;

$resources = [
    SeatMapResource::class,
];

arch('filament-seating')
    ->expect('AIArmada\FilamentSeating')
    ->not->toUse('AIArmada\Seating\Contracts');

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

arch('filament-seating config')
    ->expect('filament-seating')
    ->not->toHaveKeys(['navigation_group']);

test('plugin is registered', function (): void {
    $plugin = app(FilamentSeatingPlugin::class);
    expect($plugin->getId())->toBe('filament-seating');
});

test('filament-seating service provider is concrete', function (): void {
    $provider = new FilamentSeatingServiceProvider(app());
    expect($provider)->toBeInstanceOf(PackageServiceProvider::class);
});

test('filament-seating config uses nested navigation.group', function (): void {
    expect(config('filament-seating.navigation.group'))->toBe('Venue');
});
