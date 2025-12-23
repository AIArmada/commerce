<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentDocsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-docs')
            ->hasConfigFile('filament-docs')
            ->hasRoute('filament-docs');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentDocsPlugin::class);
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            FilamentDocsPlugin::class,
        ];
    }
}
