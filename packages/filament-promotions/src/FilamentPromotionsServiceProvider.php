<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentPromotionsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-promotions';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('filament-promotions');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentPromotionsPlugin::class);
    }

    public function packageBooted(): void
    {
        // No-op: Plugin handles resource registration
    }
}
