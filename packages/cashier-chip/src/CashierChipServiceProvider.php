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
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CashierChipServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('cashier-chip')
            ->hasConfigFile('cashier-chip')
            ->hasViews('cashier-chip')
            ->discoversMigrations()
            ->hasCommands([
                RenewSubscriptionsCommand::class,
                WebhookCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->bindInvoiceRenderer();
    }

    public function bootingPackage(): void
    {
        $this->registerEventListeners();
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
