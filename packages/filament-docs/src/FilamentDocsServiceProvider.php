<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs;

use AIArmada\CommerceSupport\Support\OwnerRouteBinding;
use AIArmada\Docs\Models\Doc;
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

    public function packageBooted(): void
    {
        OwnerRouteBinding::bind('doc', Doc::class, (bool) config('docs.owner.include_global', false));
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
