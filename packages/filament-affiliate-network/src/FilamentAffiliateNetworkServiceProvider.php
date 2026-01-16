<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork;

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
        //
    }
}
