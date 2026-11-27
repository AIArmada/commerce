<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals;

use AIArmada\FilamentSignals\Contracts\PageMarkupFetcher;
use AIArmada\FilamentSignals\Drivers\PinnedPageMarkupFetcher;
use AIArmada\FilamentSignals\Platform\ExtensionRegistry;
use AIArmada\FilamentSignals\Platform\FeatureCatalog;
use AIArmada\FilamentSignals\Platform\FeatureModule;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentSignalsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-signals')
            ->hasConfigFile()
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->bind(PageMarkupFetcher::class, PinnedPageMarkupFetcher::class);

        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            if ($module instanceof FeatureModule) {
                $module->register($this->app);
            }
        }

        $this->app->singleton(FilamentSignalsPlugin::class);
    }

    public function packageBooted(): void
    {
        foreach (array_keys(FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__)['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            if ($module instanceof FeatureModule) {
                $module->boot($this->app);
            }
        }
    }
}
