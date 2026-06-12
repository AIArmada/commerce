<?php

declare(strict_types=1);

namespace AIArmada\Contacting;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ContactingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('contacting')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }
}
