<?php

declare(strict_types=1);

namespace AIArmada\Pricing;

use Illuminate\Support\ServiceProvider;

class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/pricing.php', 'pricing');

        $this->app->singleton(Services\PriceCalculator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/pricing.php' => config_path('pricing.php'),
            ], 'pricing-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'pricing-migrations');

            $this->publishes([
                __DIR__ . '/../database/settings' => database_path('settings'),
            ], 'pricing-settings');

            // Skip loading migrations in testing - handled by TestCase
            if (! $this->app->environment('testing')) {
                $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
            }
        }

        if (is_dir(__DIR__ . '/../resources/lang')) {
            $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'pricing');
        }
    }
}
