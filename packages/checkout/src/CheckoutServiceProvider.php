<?php

declare(strict_types=1);

namespace AIArmada\Checkout;

use AIArmada\Cashier\GatewayManager;
use AIArmada\CashierChip\Cashier;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Exceptions\MissingPaymentGatewayException;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Steps\ApplyDiscountsStep;
use AIArmada\Checkout\Steps\CalculatePricingStep;
use AIArmada\Checkout\Steps\CalculateShippingStep;
use AIArmada\Checkout\Steps\CalculateTaxStep;
use AIArmada\Checkout\Steps\CreateOrderStep;
use AIArmada\Checkout\Steps\DispatchDocumentGenerationStep;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Steps\ReserveInventoryStep;
use AIArmada\Checkout\Steps\ResolveCustomerStep;
use AIArmada\Checkout\Steps\ValidateCartStep;
use AIArmada\Chip\Facades\Chip;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use AIArmada\Inventory\InventoryServiceProvider;
use AIArmada\Promotions\PromotionsServiceProvider;
use AIArmada\Tax\TaxServiceProvider;
use AIArmada\Vouchers\VouchersServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CheckoutServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('checkout')
            ->hasConfigFile()
            ->discoversMigrations();

        // Conditionally register views
        if (config('checkout.views.enabled', true)) {
            $package->hasViews('checkout');
        }

        // Conditionally register routes
        if (config('checkout.routes.enabled', true)) {
            $package->hasRoute('checkout');
        }
    }

    public function registeringPackage(): void
    {
        $this->registerStepRegistry();
        $this->registerPaymentGatewayResolver();
        $this->registerCheckoutService();
    }

    public function bootingPackage(): void
    {
        $this->validateConfiguration('checkout', [
            'defaults.currency',
        ]);

        $this->validateOwnerConfiguration();
        $this->validatePaymentGatewayConfiguration();
        $this->registerDefaultSteps();
        $this->registerOptionalIntegrations();
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'checkout',
            CheckoutService::class,
            CheckoutServiceInterface::class,
            CheckoutStepRegistry::class,
            CheckoutStepRegistryInterface::class,
            PaymentGatewayResolver::class,
            PaymentGatewayResolverInterface::class,
        ];
    }

    protected function registerStepRegistry(): void
    {
        $this->app->singleton(function (): CheckoutStepRegistry {
            $registry = new CheckoutStepRegistry;

            $enabledSteps = config('checkout.steps.enabled', []);
            foreach ($enabledSteps as $step => $enabled) {
                if (! $enabled) {
                    $registry->disable($step);
                }
            }

            $order = config('checkout.steps.order', []);
            if (! empty($order)) {
                $registry->setOrder($order);
            }

            return $registry;
        });

        $this->app->alias(CheckoutStepRegistry::class, CheckoutStepRegistryInterface::class);
        $this->app->alias(CheckoutStepRegistry::class, 'checkout.steps');
    }

    protected function registerPaymentGatewayResolver(): void
    {
        $this->app->singleton(function (): PaymentGatewayResolver {
            $resolver = new PaymentGatewayResolver(
                config('checkout.payment.default_gateway'),
                config('checkout.payment.gateway_priority', ['chip', 'cashier-chip', 'cashier']),
            );

            $this->registerPaymentProcessors($resolver);

            return $resolver;
        });

        $this->app->alias(PaymentGatewayResolver::class, PaymentGatewayResolverInterface::class);
        $this->app->alias(PaymentGatewayResolver::class, 'checkout.payment');
    }

    protected function registerPaymentProcessors(PaymentGatewayResolver $resolver): void
    {
        // Priority: cashier → cashier-chip → chip
        $gateways = (array) config('checkout.payment.gateways', []);

        if (class_exists(GatewayManager::class) && ($gateways['cashier']['enabled'] ?? true)) {
            $resolver->register('cashier', $this->app->make(Integrations\Payment\CashierProcessor::class));
        }

        if (class_exists(Cashier::class) && ($gateways['cashier-chip']['enabled'] ?? true)) {
            $resolver->register('cashier-chip', $this->app->make(Integrations\Payment\CashierChipProcessor::class));
        }

        if (class_exists(Chip::class) && ($gateways['chip']['enabled'] ?? true)) {
            $resolver->register('chip', $this->app->make(Integrations\Payment\ChipProcessor::class));
        }
    }

    protected function registerCheckoutService(): void
    {
        $this->app->singleton(CheckoutService::class, fn ($app) => new CheckoutService(
            stepRegistry: $app->make(CheckoutStepRegistryInterface::class),
            events: $app->make(Dispatcher::class),
            paymentResolver: $app->make(PaymentGatewayResolverInterface::class),
        ));

        $this->app->alias(CheckoutService::class, CheckoutServiceInterface::class);
        $this->app->alias(CheckoutService::class, 'checkout');
    }

    protected function registerDefaultSteps(): void
    {
        $registry = $this->app->make(CheckoutStepRegistryInterface::class);

        // Core steps - use lazy factory closures to defer CartManager resolution
        // until steps are actually executed (after session middleware has run)
        $registry->registerLazy('validate_cart', fn () => $this->app->make(ValidateCartStep::class));
        $registry->registerLazy('resolve_customer', fn () => $this->app->make(ResolveCustomerStep::class));
        $registry->registerLazy('calculate_pricing', fn () => $this->app->make(CalculatePricingStep::class));
        $registry->registerLazy('calculate_shipping', fn () => $this->app->make(CalculateShippingStep::class));
        $registry->registerLazy('process_payment', fn () => $this->app->make(ProcessPaymentStep::class));
        $registry->registerLazy('create_order', fn () => new CreateOrderStep(
            vouchersAdapter: $this->app->make(Integrations\VouchersAdapter::class),
        ));
        $registry->registerLazy('dispatch_documents', fn () => $this->app->make(DispatchDocumentGenerationStep::class));
    }

    protected function registerOptionalIntegrations(): void
    {
        $registry = $this->app->make(CheckoutStepRegistryInterface::class);

        // Inventory integration (optional)
        if ($this->hasInventoryPackage() && config('checkout.integrations.inventory.enabled', true)) {
            // Bind InventoryAdapter so it can be injected into ReserveInventoryStep
            $this->app->singleton(Integrations\InventoryAdapter::class);
            $registry->register('reserve_inventory', new ReserveInventoryStep(
                inventoryAdapter: $this->app->make(Integrations\InventoryAdapter::class),
            ));
        } else {
            $registry->disable('reserve_inventory');
        }

        // Tax integration (optional)
        if ($this->hasTaxPackage() && config('checkout.integrations.tax.enabled', true)) {
            $registry->register('calculate_tax', new CalculateTaxStep(
                taxAdapter: $this->app->make(Integrations\TaxAdapter::class),
            ));
        } else {
            $registry->disable('calculate_tax');
        }

        // Discounts integration (promotions + vouchers, optional)
        if ($this->hasDiscountPackages() && $this->isDiscountsEnabled()) {
            $registry->register('apply_discounts', new ApplyDiscountsStep(
                promotionsAdapter: $this->app->make(Integrations\PromotionsAdapter::class),
                vouchersAdapter: $this->app->make(Integrations\VouchersAdapter::class),
            ));
        } else {
            $registry->disable('apply_discounts');
        }
    }

    protected function validateOwnerConfiguration(): void
    {
        if (! config('checkout.owner.enabled', false)) {
            return;
        }

        if (! $this->app->bound(OwnerResolverInterface::class)) {
            throw new RuntimeException(
                'Checkout owner is enabled but no resolver is bound. ' .
                'Bind ' . OwnerResolverInterface::class . ' (recommended via COMMERCE_OWNER_RESOLVER / commerce-support config).'
            );
        }
    }

    protected function validatePaymentGatewayConfiguration(): void
    {
        // Check if at least one payment package exists
        $hasCashier = class_exists(GatewayManager::class);
        $hasCashierChip = class_exists(Cashier::class);
        $hasChip = class_exists(Chip::class);

        if (! $hasCashier && ! $hasCashierChip && ! $hasChip) {
            throw MissingPaymentGatewayException::noGatewayInstalled();
        }
    }

    protected function hasInventoryPackage(): bool
    {
        return class_exists(InventoryServiceProvider::class);
    }

    protected function hasTaxPackage(): bool
    {
        return class_exists(TaxServiceProvider::class);
    }

    protected function hasDiscountPackages(): bool
    {
        return class_exists(PromotionsServiceProvider::class)
            || class_exists(VouchersServiceProvider::class);
    }

    protected function isDiscountsEnabled(): bool
    {
        return config('checkout.integrations.promotions.enabled', true)
            || config('checkout.integrations.vouchers.enabled', true);
    }
}
