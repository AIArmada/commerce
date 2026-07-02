<?php

declare(strict_types=1);

use AIArmada\FilamentTicketing\FilamentTicketingServiceProvider;
use AIArmada\FilamentTicketing\Resources\PassHolderResource;
use AIArmada\FilamentTicketing\Resources\PassResource;
use AIArmada\FilamentTicketing\Resources\PassTransferResource;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use Spatie\LaravelPackageTools\PackageServiceProvider;

$resources = [
    TicketTypeResource::class,
    PassResource::class,
    PassHolderResource::class,
    PassTransferResource::class,
];

arch('filament-ticketing')
    ->expect('AIArmada\FilamentTicketing')
    ->not->toUse('AIArmada\Ticketing\Contracts');

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

arch('filament-ticketing config')
    ->expect('filament-ticketing')
    ->not->toHaveKeys(['navigation_group']);

test('filament-ticketing service provider is concrete', function (): void {
    $provider = new FilamentTicketingServiceProvider(app());
    expect($provider)->toBeInstanceOf(PackageServiceProvider::class);
});

test('filament-ticketing config is accessible', function (): void {
    expect(config('filament-ticketing.navigation.group'))->toBe('Ticketing');
});
