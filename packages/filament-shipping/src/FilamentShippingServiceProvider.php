<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping;

use AIArmada\FilamentShipping\Services\CartBridge;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentShippingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-shipping')
            ->hasViews('filament-shipping');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CartBridge::class);
    }
}
