<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart;

use AIArmada\Cart\Events\CartAbandoned;
use AIArmada\FilamentCart\EntryPoints\Http\DownloadCart;
use AIArmada\FilamentCart\EntryPoints\Listeners\SendCartAbandonedNotification;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentCartServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-cart')
            ->hasConfigFile('filament-cart')
            ->hasViews('filament-cart');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentCartPlugin::class);
        $this->app->singleton(DownloadCart::class);
    }

    public function bootingPackage(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../database/settings' => database_path('settings'),
        ], 'filament-cart-settings');
    }

    public function packageBooted(): void
    {
        $this->registerEventListeners();
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            DownloadCart::class,
        ];
    }

    /**
     * Register event listeners for cart synchronization
     */
    protected function registerEventListeners(): void
    {
        // Notifications are an adapter concern; snapshot state and lifecycle
        // events are owned by the core cart package.
        if ((bool) config('filament-cart.notifications.abandoned_cart.enabled', true)) {
            $this->app['events']->listen(CartAbandoned::class, SendCartAbandonedNotification::class);
        }
    }
}
