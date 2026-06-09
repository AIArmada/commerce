<?php

declare(strict_types=1);

namespace AIArmada\Pricing;

use AIArmada\Pricing\Contracts\CustomerPriceResolverInterface;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Contracts\SegmentPriceResolverInterface;
use AIArmada\Pricing\Contracts\TierResolverInterface;
use AIArmada\Pricing\Support\CustomerPriceResolver;
use AIArmada\Pricing\Support\PricingIntegrationRegistrar;
use AIArmada\Pricing\Support\SegmentPriceResolver;
use AIArmada\Pricing\Support\TierResolver;
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
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TierResolverInterface::class, TierResolver::class);
        $this->app->singleton(CustomerPriceResolverInterface::class, CustomerPriceResolver::class);
        $this->app->singleton(SegmentPriceResolverInterface::class, SegmentPriceResolver::class);
        $this->app->singleton(Services\PriceCalculator::class);
        $this->app->alias(Services\PriceCalculator::class, PriceCalculatorInterface::class);
        $this->app->singleton(PricingIntegrationRegistrar::class);
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
