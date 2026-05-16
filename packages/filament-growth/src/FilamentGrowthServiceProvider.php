<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentGrowthServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-growth')
            ->hasViews('filament-growth')
            ->hasConfigFile('filament-growth');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentGrowthPlugin::class);
    }
}