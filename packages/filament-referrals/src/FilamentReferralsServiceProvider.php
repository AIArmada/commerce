<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentReferralsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-referrals';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentReferralsPlugin::class);
    }

    public function packageBooted(): void
    {
        // The plugin registers the package resources and widget.
    }
}
