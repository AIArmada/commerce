<?php

declare(strict_types=1);

use AIArmada\Affiliates\AffiliatesServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

describe('Affiliates migrations', function (): void {
    test('are publishable but not auto-run by default', function (): void {
        $provider = app()->getProvider(AffiliatesServiceProvider::class);

        expect($provider)->not()->toBeNull();

        $reflection = new \ReflectionClass($provider);
        $packageProperty = $reflection->getProperty('package');
        $packageProperty->setAccessible(true);

        /** @var object $package */
        $package = $packageProperty->getValue($provider);

        expect(property_exists($package, 'runsMigrations'))->toBeTrue();
        expect($package->runsMigrations)->toBeFalse();

        $pathsToPublish = ServiceProvider::pathsToPublish(AffiliatesServiceProvider::class, 'affiliates-migrations');

        expect($pathsToPublish)->toBeArray()->not()->toBeEmpty();

        $destinationPaths = array_values($pathsToPublish);

        $publishesCreateAffiliatesTable = collect($destinationPaths)
            ->contains(fn (string $path): bool => str_ends_with($path, '_create_affiliates_table.php'));

        expect($publishesCreateAffiliatesTable)->toBeTrue();
    });

    test('registers an install command to publish migrations', function (): void {
        $commands = Artisan::all();

        expect($commands)->toBeArray();
        expect(array_key_exists('affiliates:install', $commands))->toBeTrue();
    });
});
