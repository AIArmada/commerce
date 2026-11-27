<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentShippingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-shipping')
            ->hasConfigFile()
            ->hasViews('filament-shipping');
    }

}
