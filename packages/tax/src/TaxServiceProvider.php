<?php

declare(strict_types=1);

namespace AIArmada\Tax;

use Illuminate\Support\ServiceProvider;

class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tax.php', 'tax');

        $this->app->singleton(Services\TaxCalculator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/tax.php' => config_path('tax.php'),
            ], 'tax-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'tax-migrations');

            $this->publishes([
                __DIR__ . '/../database/settings' => database_path('settings'),
            ], 'tax-settings');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }
}
