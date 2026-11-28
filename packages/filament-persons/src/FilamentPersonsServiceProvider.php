<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons;

use AIArmada\FilamentPersons\Queries\LanguageOptions;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentPersonsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-persons')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentPersonsPlugin::class);
        $this->app->scoped(LanguageOptions::class);
    }
}
