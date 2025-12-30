<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentProductsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-products')
            ->hasViews('filament-products')
            ->hasTranslations();
    }
}
