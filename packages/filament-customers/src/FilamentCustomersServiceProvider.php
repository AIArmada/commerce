<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentCustomersServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-customers')
            ->hasViews('filament-customers');
    }
}
