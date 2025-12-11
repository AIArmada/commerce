<?php

declare(strict_types=1);

namespace AIArmada\Products;

use Illuminate\Support\ServiceProvider;

class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/products.php', 'products');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/products.php' => config_path('products.php'),
            ], 'products-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'products-migrations');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'products');

        $this->registerPolicies();
    }

    protected function registerPolicies(): void
    {
        // Policies will be registered here
    }
}
