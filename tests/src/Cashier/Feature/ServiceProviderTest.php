<?php

declare(strict_types=1);

use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Composer\InstalledVersions;
use Illuminate\Support\ServiceProvider;

uses(CashierTestCase::class);

describe('CashierServiceProvider', function (): void {
    it('registers the GatewayManager as a singleton', function (): void {
        $manager1 = $this->app->make(GatewayManager::class);
        $manager2 = $this->app->make(GatewayManager::class);

        expect($manager1)->toBe($manager2);
    });

    it('makes Stripe migrations publishable and auto-loads them when unpublished', function (): void {
        $provider = $this->app->getProvider(CashierServiceProvider::class);

        expect($provider)->not()->toBeNull();

        $migrationsPath = base_path('vendor/laravel/cashier/database/migrations');
        $isInstalled = class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('laravel/cashier');

        if ($isInstalled && is_dir($migrationsPath)) {
            $pathsToPublish = ServiceProvider::pathsToPublish(CashierServiceProvider::class, 'cashier-stripe-migrations');
            $migratorPaths = collect(app('migrator')->paths());

            expect($pathsToPublish)->toBeArray()->not()->toBeEmpty();

            if (glob(database_path('migrations/*_create_customer_columns.php')) === []) {
                expect($migratorPaths->contains($migrationsPath . '/2019_05_03_000001_create_customer_columns.php'))
                    ->toBeTrue();
            }
        } else {
            $this->markTestSkipped('laravel/cashier is not installed.');
        }
    });
});
