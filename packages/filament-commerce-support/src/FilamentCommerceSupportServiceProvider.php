<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentCommerceSupportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-commerce-support')
            ->hasConfigFile('filament-commerce-support')
            ->hasViews('filament-commerce-support');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentCommerceSupportPlugin::class);
    }
}
