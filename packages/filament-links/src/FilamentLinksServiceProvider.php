<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentLinksServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-links')
            ->hasConfigFile('filament-links');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentLinksPlugin::class);
    }
}
