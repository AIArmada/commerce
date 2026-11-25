<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork;

use AIArmada\FilamentAffiliateNetwork\Platform\ExtensionRegistry;
use AIArmada\FilamentAffiliateNetwork\Platform\FeatureCatalog;
use AIArmada\FilamentAffiliateNetwork\Platform\FeatureModule;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentAffiliateNetworkServiceProvider extends PackageServiceProvider
{
    /** @var array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>}|null */
    private ?array $catalog = null;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-affiliate-network')
            ->hasConfigFile('filament-affiliate-network')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $catalog = $this->catalog();
        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));
        $this->app->singleton(FilamentAffiliateNetworkPlugin::class);

        foreach (array_keys($catalog['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');

            if ($module instanceof FeatureModule) {
                $module->register($this->app);
            }
        }
    }

    public function packageBooted(): void
    {
        foreach (array_keys($this->catalog()['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');

            if ($module instanceof FeatureModule) {
                $module->boot($this->app);
            }
        }
    }

    /** @return array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>} */
    private function catalog(): array
    {
        return $this->catalog ??= FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
    }
}
