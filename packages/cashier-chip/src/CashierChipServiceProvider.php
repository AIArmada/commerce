<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\EntryPoints\Commands\RenewSubscriptionsCommand;
use AIArmada\CashierChip\Contracts\CustomerOwnerResolverInterface;
use AIArmada\CashierChip\Contracts\InvoiceRenderer;
use AIArmada\CashierChip\Contracts\PaymentMethodStoreInterface;
use AIArmada\CashierChip\Invoices\DocsInvoiceRenderer;
use AIArmada\CashierChip\EntryPoints\Listeners\HandlePurchasePaid;
use AIArmada\CashierChip\EntryPoints\Listeners\HandlePurchasePaymentFailure;
use AIArmada\CashierChip\EntryPoints\Listeners\HandlePurchasePreauthorized;
use AIArmada\CashierChip\Payment\PaymentMethodStore;
use AIArmada\CashierChip\Support\DefaultCustomerOwnerResolver;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Docs\Services\DocService;
use Illuminate\Support\Facades\Event;
use Laravel\Octane\Events\RequestReceived;
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
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                RenewSubscriptionsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->bindInvoiceRenderer();
        $this->bindPaymentMethodStore();
        $this->bindCustomerOwnerResolver();
    }

    public function bootingPackage(): void
    {
        $this->registerEventListeners();
        $this->registerOctaneListeners();

        $this->app->booted(static function (): void {
            Cashier::rememberOctaneDefaults();
        });
    }

    protected function bindInvoiceRenderer(): void
    {
        $this->app->bind(InvoiceRenderer::class, function ($app) {
            $renderer = config('cashier-chip.invoices.renderer');

            if ($renderer && class_exists($renderer)) {
                return $app->make($renderer);
            }

            if (class_exists(DocService::class)) {
                return $app->make(DocsInvoiceRenderer::class);
            }

            throw new RuntimeException('Docs package is required for invoice rendering. Install aiarmada/docs.');
        });
    }

    private function bindCustomerOwnerResolver(): void
    {
        $this->app->bind(CustomerOwnerResolverInterface::class, function ($app): CustomerOwnerResolverInterface {
            $configured = config('cashier-chip.features.owner.customer_resolver');

            if (is_string($configured) && $configured !== '' && class_exists($configured)) {
                $resolved = $app->make($configured);

                if ($resolved instanceof CustomerOwnerResolverInterface) {
                    return $resolved;
                }
            }

            return $app->make(DefaultCustomerOwnerResolver::class);
        });
    }

    protected function bindPaymentMethodStore(): void
    {
        $this->app->singleton(PaymentMethodStore::class, fn (): PaymentMethodStore => new PaymentMethodStore);
        $this->app->alias(PaymentMethodStore::class, PaymentMethodStoreInterface::class);
    }

    protected function registerEventListeners(): void
    {
        if (! class_exists(PurchasePaid::class)) {
            return;
        }

        Event::listen(PurchasePaid::class, HandlePurchasePaid::class);
        Event::listen(PurchasePaymentFailure::class, HandlePurchasePaymentFailure::class);
        Event::listen(PurchasePreauthorized::class, HandlePurchasePreauthorized::class);
    }

    private function registerOctaneListeners(): void
    {
        if (! class_exists(RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(RequestReceived::class, static function (): void {
            Cashier::restoreOctaneDefaults();
        });
    }
}
