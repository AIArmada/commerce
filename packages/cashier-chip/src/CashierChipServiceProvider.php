<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Console\RenewSubscriptionsCommand;
use AIArmada\CashierChip\Console\WebhookCommand;
use AIArmada\CashierChip\Contracts\InvoiceRenderer;
use AIArmada\CashierChip\Invoices\DocsInvoiceRenderer;
use AIArmada\CashierChip\Listeners\HandleBillingCancelled;
use AIArmada\CashierChip\Listeners\HandlePurchasePaid;
use AIArmada\CashierChip\Listeners\HandlePurchasePaymentFailure;
use AIArmada\CashierChip\Listeners\HandlePurchasePreauthorized;
use AIArmada\CashierChip\Listeners\HandleSubscriptionChargeFailure;
use AIArmada\Chip\Events\BillingCancelled;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;
use AIArmada\Docs\Services\DocService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class CashierChipServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerResources();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerEventListeners();
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
            __DIR__ . '/../config/cashier-chip.php',
            'cashier-chip'
        );
    }

    /**
     * Bind the default invoice renderer.
     */
    protected function bindInvoiceRenderer(): void
    {
        $this->app->bind(InvoiceRenderer::class, function ($app) {
            // Check for custom renderer first
            $renderer = config('cashier-chip.invoices.renderer');

            if ($renderer && class_exists($renderer)) {
                return $app->make($renderer);
            }

            // Use docs package for invoice rendering
            if (class_exists(DocService::class)) {
                return $app->make(DocsInvoiceRenderer::class);
            }

            throw new RuntimeException('Docs package is required for invoice rendering. Install aiarmada/docs.');
        });
    }

    /**
     * Register the package resources.
     */
    protected function registerResources(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cashier-chip');
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cashier-chip.php' => $this->app->configPath('cashier-chip.php'),
            ], 'cashier-chip-config');

            $this->publishesMigrations([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'cashier-chip-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => $this->app->resourcePath('views/vendor/cashier-chip'),
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

    /**
     * Register event listeners for chip package events.
     *
     * These listeners handle cashier-chip billing logic when chip events fire.
     */
    protected function registerEventListeners(): void
    {
        // Only register if chip package is available
        if (! class_exists(PurchasePaid::class)) {
            return;
        }

        Event::listen(PurchasePaid::class, HandlePurchasePaid::class);
        Event::listen(PurchasePaymentFailure::class, HandlePurchasePaymentFailure::class);
        Event::listen(PurchasePreauthorized::class, HandlePurchasePreauthorized::class);
        Event::listen(PurchaseSubscriptionChargeFailure::class, HandleSubscriptionChargeFailure::class);
        Event::listen(BillingCancelled::class, HandleBillingCancelled::class);
    }
}
