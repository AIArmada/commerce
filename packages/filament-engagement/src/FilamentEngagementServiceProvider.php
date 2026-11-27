<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement;

use AIArmada\FilamentEngagement\Platform\ExtensionRegistry;
use AIArmada\FilamentEngagement\Platform\FeatureCatalog;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentEngagementServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-engagement')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            $module->register($this->app);
        }

        $this->app->singleton(FilamentEngagementPlugin::class);
    }

    public function packageBooted(): void
    {
        foreach (array_keys(FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__)['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            $module->boot($this->app);
        }
    }
}
