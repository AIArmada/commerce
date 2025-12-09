<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentCashierServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-cashier')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentCashierPlugin::class);
        $this->app->singleton(GatewayDetector::class);
    }

    public function packageBooted(): void
    {
        //
    }
}
