<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork;

use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\AffiliateNetwork\Services\SiteVerificationService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AffiliateNetworkServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('affiliate-network')
            ->hasConfigFile('affiliate-network')
            ->discoversMigrations()
            ->hasRoutes(['api']);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SiteVerificationService::class);
        $this->app->singleton(OfferManagementService::class);
        $this->app->singleton(OfferLinkService::class);
    }

    public function packageBooted(): void
    {
        //
    }
}
