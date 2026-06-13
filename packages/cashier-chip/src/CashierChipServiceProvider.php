<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Console\RenewSubscriptionsCommand;
use AIArmada\CashierChip\Console\WebhookCommand;
use AIArmada\CashierChip\Contracts\InvoiceRenderer;
use AIArmada\CashierChip\Contracts\PaymentMethodStoreInterface;
use AIArmada\CashierChip\Invoices\DocsInvoiceRenderer;
use AIArmada\CashierChip\Listeners\HandleBillingCancelled;
use AIArmada\CashierChip\Listeners\HandlePurchasePaid;
use AIArmada\CashierChip\Listeners\HandlePurchasePaymentFailure;
use AIArmada\CashierChip\Listeners\HandlePurchasePreauthorized;
use AIArmada\CashierChip\Listeners\HandleSubscriptionChargeFailure;
use AIArmada\CashierChip\Payment\PaymentMethodStore;
use AIArmada\Chip\Events\BillingCancelled;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;
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
                WebhookCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->registerClassAliases();
        $this->bindInvoiceRenderer();
        $this->bindPaymentMethodStore();
    }

    public function bootingPackage(): void
    {
        $this->registerEventListeners();
        $this->registerOctaneListeners();

        $this->app->booted(static function (): void {
            Cashier::rememberOctaneDefaults();
        });
    }

    /**
     * Register class aliases for moved entity classes (backward compatibility).
     *
     * Wires an autoloader that redirects old root-namespace names to the new
     * domain-namespace homes so existing imports in downstream packages keep
     * working without manual updates.
     */
    protected function registerClassAliases(): void
    {
        $legacyMap = [
            'Cashier' => 'Billing\\Cashier',
            'Checkout' => 'Billing\\Checkout',
            'CheckoutBuilder' => 'Billing\\CheckoutBuilder',
            'Coupon' => 'Billing\\Coupon',
            'Discount' => 'Billing\\Discount',
            'PromotionCode' => 'Billing\\PromotionCode',
            'Payment' => 'Payment\\Payment',
            'PaymentMethod' => 'Payment\\PaymentMethod',
            'PaymentMethodStore' => 'Payment\\PaymentMethodStore',
            'StoredPaymentMethod' => 'Payment\\StoredPaymentMethod',
            'InvoicePayment' => 'Payment\\InvoicePayment',
            'Subscription' => 'Subscription\\Subscription',
            'SubscriptionBuilder' => 'Subscription\\SubscriptionBuilder',
            'SubscriptionItem' => 'Subscription\\SubscriptionItem',
            'Invoice' => 'Invoice\\Invoice',
            'InvoiceLineItem' => 'Invoice\\InvoiceLineItem',
        ];

        $prefix = 'AIArmada\\CashierChip\\';

        spl_autoload_register(function (string $class) use ($prefix, $legacyMap): void {
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $shortName = mb_substr($class, mb_strlen($prefix));

            if (! isset($legacyMap[$shortName])) {
                return;
            }

            if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
                return;
            }

            $target = $prefix . $legacyMap[$shortName];

            if (! class_exists($target)) {
                return;
            }

            class_alias($target, $class, false);
        }, true, true);
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
        Event::listen(PurchaseSubscriptionChargeFailure::class, HandleSubscriptionChargeFailure::class);
        Event::listen(BillingCancelled::class, HandleBillingCancelled::class);
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
