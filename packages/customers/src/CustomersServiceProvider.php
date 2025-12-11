<?php

declare(strict_types=1);

namespace AIArmada\Customers;

use Illuminate\Support\ServiceProvider;

class CustomersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/customers.php', 'customers');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/customers.php' => config_path('customers.php'),
            ], 'customers-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'customers-migrations');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'customers');
    }
}
