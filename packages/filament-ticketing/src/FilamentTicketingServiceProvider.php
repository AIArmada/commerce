<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing;

use AIArmada\FilamentTicketing\Support\TicketableTypeRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentTicketingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-ticketing')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentTicketingPlugin::class);
        $this->app->singleton(TicketableTypeRegistry::class);
    }
}
