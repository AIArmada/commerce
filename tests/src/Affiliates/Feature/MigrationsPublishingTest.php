<?php

declare(strict_types=1);

use AIArmada\Affiliates\AffiliatesServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

describe('Affiliates migrations', function (): void {
    test('are auto-run by default', function (): void {
        $provider = app()->getProvider(AffiliatesServiceProvider::class);

        expect($provider)->not()->toBeNull();

        $reflection = new ReflectionClass($provider);
        $packageProperty = $reflection->getProperty('package');

        /** @var object $package */
        $package = $packageProperty->getValue($provider);

        expect(property_exists($package, 'runsMigrations'))->toBeTrue();
        expect($package->runsMigrations)->toBeTrue();
    });

    test('does not create unsupported per-affiliate api credentials', function (): void {
        expect(Schema::hasColumn(config('affiliates.database.tables.affiliates', 'affiliates'), 'api_token'))->toBeFalse();
    });

    test('registers expected console commands', function (): void {
        $commands = Artisan::all();

        expect($commands)->toBeArray();
        expect(array_key_exists('affiliates:payout:export', $commands))->toBeTrue();
        expect(array_key_exists('affiliates:aggregate-daily', $commands))->toBeTrue();
        expect(array_key_exists('affiliates:process-ranks', $commands))->toBeTrue();
        expect(array_key_exists('affiliates:process-payouts', $commands))->toBeTrue();
        expect(array_key_exists('affiliates:process-maturity', $commands))->toBeTrue();
    });
});
