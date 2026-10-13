<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentCommunicationsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-communications')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentCommunicationsPlugin::class);
    }
}
