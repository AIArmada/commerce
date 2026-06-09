<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentChipServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-chip')
            ->hasConfigFile()
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentChipPlugin::class);
    }

    public function packageBooted(): void
    {
        FilamentChipMacros::register();
    }
}
