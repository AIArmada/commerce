<?php

declare(strict_types=1);

namespace AIArmada\Cashier;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the unified multi-gateway Cashier package.
 *
 * This package provides a unified interface for multiple payment gateways.
 * It does NOT create its own tables - subscriptions are stored in the
 * respective gateway package's tables (subscriptions for Stripe,
 * chip_subscriptions for CHIP).
 */
class CashierServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cashier.php', 'cashier');

        $this->app->singleton(GatewayManager::class, function ($app) {
            return new GatewayManager($app);
        });

        $this->app->alias(GatewayManager::class, 'cashier');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();
    }

    /**
>>>>>>> Stashed changes
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            GatewayManager::class,
            'cashier',
        ];
    }

    /**
<<<<<<< Updated upstream
=======
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cashier.php' => $this->app->configPath('cashier.php'),
            ], 'cashier-config');
        }
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if (Cashier::$registersRoutes) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cashier.php' => $this->app->configPath('cashier.php'),
            ], 'cashier-config');
        }
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if (Cashier::$registersRoutes) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
