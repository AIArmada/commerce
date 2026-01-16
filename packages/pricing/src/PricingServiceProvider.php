<?php

declare(strict_types=1);

namespace AIArmada\Pricing;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class PricingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('pricing')
            ->hasConfigFile()
            ->hasTranslations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Services\PriceCalculator::class);
        $this->app->alias(Services\PriceCalculator::class, Contracts\PriceCalculatorInterface::class);
    }

    public function bootingPackage(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../database/settings' => database_path('settings'),
        ], 'pricing-settings');
    }
}
