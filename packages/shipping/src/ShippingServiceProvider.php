<?php

declare(strict_types=1);

namespace AIArmada\Shipping;

use Illuminate\Support\ServiceProvider;

class ShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/shipping.php',
            'shipping'
        );

        $this->app->singleton(ShippingManager::class, function ($app) {
            return new ShippingManager($app);
        });

        $this->app->alias(ShippingManager::class, 'shipping');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/shipping.php' => config_path('shipping.php'),
            ], 'shipping-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'shipping-migrations');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->registerEventListeners();
        $this->registerCommands();
    }

    protected function registerEventListeners(): void
    {
        // Event listeners will be registered here
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Commands will be registered here
            ]);
        }
    }
}
