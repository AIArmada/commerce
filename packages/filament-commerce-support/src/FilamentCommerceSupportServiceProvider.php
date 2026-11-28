<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport;

use AIArmada\CommerceSupport\Support\SettingsMigrationPath;
use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;
use Laravel\Octane\Events\RequestReceived;
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
                NavigationConfigurator::apply();
            }
        });

        if (class_exists(RequestReceived::class)) {
            $this->app['events']->listen(RequestReceived::class, static function (): void {
                NavigationConfigurator::refreshForRequest();
            });
        }
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
        SettingsMigrationPath::register($this->app, __DIR__ . '/../database/settings');
    }
}
