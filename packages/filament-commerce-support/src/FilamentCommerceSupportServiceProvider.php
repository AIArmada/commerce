<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport;

use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelSettings\Events\SettingsSaved;

class FilamentCommerceSupportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-commerce-support')
            ->hasConfigFile('filament-commerce-support')
            ->hasViews('filament-commerce-support');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentCommerceSupportPlugin::class);

        $this->registerSettingsMigrationPath();
        $this->app['events']->listen(SettingsSaved::class, static function (SettingsSaved $event): void {
            if ($event->settings instanceof CommerceNavigationSettings) {
                NavigationConfigurator::restoreGenuineDefaults();
            }
        });
    }

    public function bootingPackage(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/settings' => database_path('settings'),
            ], 'commerce-navigation-settings');
        }
    }

    private function registerSettingsMigrationPath(): void
    {
        $packagePath = __DIR__ . '/../database/settings';

        if (! is_dir($packagePath)) {
            return;
        }

        $paths = config('settings.migrations_paths', []);

        if (! in_array($packagePath, $paths, true)) {
            $paths[] = $packagePath;

            config(['settings.migrations_paths' => $paths]);
        }
    }
}
