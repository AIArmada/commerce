<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing;

use AIArmada\FilamentPricing\Policies\PriceListPolicy;
use AIArmada\FilamentPricing\Policies\PricePolicy;
use AIArmada\FilamentPricing\Policies\PriceTierPolicy;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentPricingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-pricing')
            ->hasConfigFile('filament-pricing')
            ->hasViews('filament-pricing');
    }

    public function bootingPackage(): void
    {
        Gate::policy(PriceList::class, PriceListPolicy::class);
        Gate::policy(Price::class, PricePolicy::class);
        Gate::policy(PriceTier::class, PriceTierPolicy::class);
    }
}
