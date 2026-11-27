<?php

declare(strict_types=1);

namespace AIArmada\Contacting;

use AIArmada\Contacting\Contracts\PublicHtmlFetcher;
use AIArmada\Contacting\Drivers\PinnedPublicHtmlFetcher;
use AIArmada\Contacting\Platform\ExtensionRegistry;
use AIArmada\Contacting\Platform\FeatureCatalog;
use AIArmada\Contacting\Platform\FeatureModule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ContactingServiceProvider extends PackageServiceProvider
{
    /** @var array<string, FeatureModule> */
    private array $featureModules = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('contacting')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(PublicHtmlFetcher::class, PinnedPublicHtmlFetcher::class);

        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $class = __NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule';
            $module = $this->app->make($class);

            if (! $module instanceof FeatureModule) {
                throw new \InvalidArgumentException("Invalid contacting feature module [{$class}].");
            }

            $this->featureModules[$feature] = $module;
            $module->register($this->app);
        }
    }

    public function packageBooted(): void
    {
        foreach ($this->featureModules as $module) {
            $module->boot($this->app);
        }
    }
}
