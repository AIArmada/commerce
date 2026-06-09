<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentAffiliateNetworkServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-affiliate-network')
            ->hasConfigFile('filament-affiliate-network')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentAffiliateNetworkPlugin::class);
    }

    public function packageBooted(): void
    {
        Gate::policy(AffiliateSite::class, Policies\AffiliateSitePolicy::class);
        Gate::policy(AffiliateOfferCategory::class, Policies\AffiliateOfferCategoryPolicy::class);
        Gate::policy(AffiliateOffer::class, Policies\AffiliateOfferPolicy::class);
        Gate::policy(AffiliateOfferApplication::class, Policies\AffiliateOfferApplicationPolicy::class);
    }
}
