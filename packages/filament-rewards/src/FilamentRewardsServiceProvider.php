<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentRewardsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-rewards';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('filament-rewards');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentRewardsPlugin::class);
    }

    public function packageBooted(): void
    {
        // No-op: Plugin handles resource registration
    }
}
