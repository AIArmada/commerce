<?php

declare(strict_types=1);

namespace AIArmada\Tax;

use AIArmada\CommerceSupport\Support\SettingsMigrationPath;
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Services\RateApplier\StandardRateApplier;
use AIArmada\Tax\Services\RateApplier\TaxRateApplierInterface;
use AIArmada\Tax\Services\TaxCalculator;
use AIArmada\Tax\Services\ZoneResolver\CompositeZoneResolver;
use AIArmada\Tax\Services\ZoneResolver\TaxZoneResolverInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class TaxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('tax')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(TaxZoneResolverInterface::class, CompositeZoneResolver::class);
        $this->app->singleton(TaxRateApplierInterface::class, StandardRateApplier::class);

        $this->app->scoped(TaxCalculatorInterface::class, TaxCalculator::class);
        $this->app->alias(TaxCalculatorInterface::class, 'tax');

        $this->registerSettingsMigrationPath();
    }

    public function bootingPackage(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/settings' => database_path('settings'),
            ], 'tax-settings');
        }
    }

    private function registerSettingsMigrationPath(): void
    {
        SettingsMigrationPath::register($this->app, __DIR__ . '/../database/settings');
    }
}
