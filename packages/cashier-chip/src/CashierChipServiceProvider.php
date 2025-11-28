<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Console\RenewSubscriptionsCommand;
use AIArmada\CashierChip\Console\WebhookCommand;
use AIArmada\CashierChip\Contracts\InvoiceRenderer;
use AIArmada\CashierChip\Invoices\DompdfInvoiceRenderer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CashierChipServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerLogger();
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configure();
        $this->bindInvoiceRenderer();
    }

    /**
     * Setup the configuration for Cashier.
     */
    protected function configure(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cashier-chip.php', 'cashier-chip'
        );
    }

    /**
     * Bind the default invoice renderer.
     */
    protected function bindInvoiceRenderer(): void
    {
        $this->app->bind(InvoiceRenderer::class, function ($app) {
            $renderer = config('cashier-chip.invoices.renderer');

            if ($renderer && class_exists($renderer)) {
                return $app->make($renderer);
            }

            // Fallback to DompdfInvoiceRenderer if available
            if (class_exists(DompdfInvoiceRenderer::class)) {
                return $app->make(DompdfInvoiceRenderer::class);
            }

            return null;
        });
    }

    /**
     * Register the logger.
     */
    protected function registerLogger(): void
    {
        // Logger is handled by the chip package
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if (CashierChip::$registersRoutes) {
            Route::group([
                'prefix' => config('cashier-chip.path'),
                'namespace' => 'AIArmada\CashierChip\Http\Controllers',
                'as' => 'cashier-chip.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }
    }

    /**
     * Register the package resources.
     */
    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier-chip');
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cashier-chip.php' => $this->app->configPath('cashier-chip.php'),
            ], 'cashier-chip-config');

            $publishesMigrationsMethod = method_exists($this, 'publishesMigrations')
                ? 'publishesMigrations'
                : 'publishes';

            $this->{$publishesMigrationsMethod}([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'cashier-chip-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/cashier-chip'),
            ], 'cashier-chip-views');
        }
    }

    /**
     * Register the package's commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RenewSubscriptionsCommand::class,
                WebhookCommand::class,
            ]);
        }
    }
}
