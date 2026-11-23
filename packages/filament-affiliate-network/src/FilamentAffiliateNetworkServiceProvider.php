<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Policies\AffiliateOfferApplicationPolicy;
use AIArmada\FilamentAffiliateNetwork\Policies\AffiliateOfferCategoryPolicy;
use AIArmada\FilamentAffiliateNetwork\Policies\AffiliateOfferPolicy;
use AIArmada\FilamentAffiliateNetwork\Policies\AffiliateSitePolicy;
use AIArmada\FilamentAffiliateNetwork\Policies\MerchantStatementPolicy;
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
        Gate::policy(AffiliateSite::class, AffiliateSitePolicy::class);
        Gate::policy(AffiliateOfferCategory::class, AffiliateOfferCategoryPolicy::class);
        Gate::policy(AffiliateOffer::class, AffiliateOfferPolicy::class);
        Gate::policy(AffiliateOfferApplication::class, AffiliateOfferApplicationPolicy::class);
        Gate::policy(MerchantStatement::class, MerchantStatementPolicy::class);
    }
}
