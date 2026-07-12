<?php

declare(strict_types=1);

namespace AIArmada\Checkout;

use AIArmada\Cashier\GatewayManager;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Checkout\Actions\FinalizeCheckoutSession;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Exceptions\MissingPaymentGatewayException;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\Steps\CalculatePricingStep;
use AIArmada\Checkout\Steps\CalculateShippingStep;
use AIArmada\Checkout\Steps\CreateOrderStep;
use AIArmada\Checkout\Steps\DispatchDocumentGenerationStep;
use AIArmada\Checkout\Steps\PersistCustomerStep;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Steps\ResolveCustomerStep;
use AIArmada\Checkout\Steps\ValidateCartStep;
use AIArmada\Checkout\Support\CheckoutStepOrderPolicy;
use AIArmada\Checkout\Support\ChipIntegrationRegistrar;
use AIArmada\Checkout\Support\RegisterBuiltInPaymentProcessors;
use AIArmada\Checkout\Support\RegisterCheckoutOptionalSteps;
use AIArmada\Chip\Facades\Chip;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookClientServiceProvider;

final class CheckoutServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('checkout')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();

        if (config('checkout.views.enabled', true)) {
            $package->hasViews('checkout');
        }

        if (config('checkout.routes.enabled', true)) {
            $package->hasRoute('checkout');
        }
    }

    public function registeringPackage(): void
    {
        $this->configureSpatieWebhookClient();
        $this->registerSpatieWebhookClient();
        $this->registerStepRegistry();
        $this->registerPaymentGatewayResolver();
        $this->registerCheckoutService();
    }

    public function bootingPackage(): void
    {
        $this->validateConfiguration('checkout', [
            'defaults.currency',
        ]);

        $this->validateStepConfiguration();
        $this->validateOwnerConfiguration();
        $this->validatePaymentGatewayConfiguration();
        $this->registerDefaultSteps();
        $this->registerOptionalIntegrations();
    }

    protected function validateStepConfiguration(): void
    {
        $createOrderEnabled = (bool) config('checkout.steps.enabled.create_order', true);
        $persistCustomerEnabled = (bool) config('checkout.steps.enabled.persist_customer', true);

        if ($createOrderEnabled && ! $persistCustomerEnabled) {
            throw new RuntimeException(
                'Invalid checkout step configuration: step [persist_customer] must be enabled when [create_order] is enabled.'
            );
        }
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

            app(RegisterBuiltInPaymentProcessors::class)->register($resolver);

            return $resolver;
        });

        $this->app->alias(PaymentGatewayResolver::class, PaymentGatewayResolverInterface::class);
        $this->app->alias(PaymentGatewayResolver::class, 'checkout.payment');
    }

    protected function registerCheckoutService(): void
    {
        $this->app->singleton(CheckoutService::class, fn ($app) => new CheckoutService(
            stepRegistry: $app->make(CheckoutStepRegistryInterface::class),
            events: $app->make(Dispatcher::class),
            stepExecutor: $app->make(StepExecutor::class),
            finalizer: $app->make(FinalizeCheckoutSession::class),
            paymentResolver: $app->make(PaymentGatewayResolverInterface::class),
        ));

        $this->app->alias(CheckoutService::class, CheckoutServiceInterface::class);
        $this->app->alias(CheckoutService::class, 'checkout');
    }

    protected function registerDefaultSteps(): void
    {
        $registry = $this->app->make(CheckoutStepRegistryInterface::class);

        $registry->registerLazy('validate_cart', fn () => $this->app->make(ValidateCartStep::class));
        $registry->registerLazy('resolve_customer', fn () => $this->app->make(ResolveCustomerStep::class));
        $registry->registerLazy('calculate_pricing', fn () => $this->app->make(CalculatePricingStep::class));
        $registry->registerLazy('calculate_shipping', fn () => $this->app->make(CalculateShippingStep::class));
        $registry->registerLazy('process_payment', fn () => $this->app->make(ProcessPaymentStep::class));
        $registry->registerLazy('persist_customer', fn () => $this->app->make(PersistCustomerStep::class));
        $registry->registerLazy('create_order', fn () => new CreateOrderStep(
            vouchersAdapter: $this->app->make(Integrations\VouchersAdapter::class),
        ));
        $registry->registerLazy('dispatch_documents', fn () => $this->app->make(DispatchDocumentGenerationStep::class));
    }

    protected function registerOptionalIntegrations(): void
    {
        $registry = $this->app->make(CheckoutStepRegistryInterface::class);

        app(RegisterCheckoutOptionalSteps::class)->register($registry);

        $this->registerChipIntegration();

        $order = $registry->getOrder();
        if (! empty($order)) {
            $normalizedOrder = app(CheckoutStepOrderPolicy::class)->normalizeInventoryStepOrder($registry, $order);
            $registry->setOrder($normalizedOrder);
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

    protected function configureSpatieWebhookClient(): void
    {
        if (! class_exists(WebhookCall::class)) {
            return;
        }

        $configName = 'checkout.webhook';
        $configs = config('webhook-client.configs', []);

        if (! is_array($configs)) {
            $configs = [];
        }

        $configs = array_values(array_filter($configs, static function (mixed $existingConfig): bool {
            if (! is_array($existingConfig)) {
                return false;
            }

            $processWebhookJob = $existingConfig['process_webhook_job'] ?? null;

            return is_string($processWebhookJob) && $processWebhookJob !== '';
        }));

        foreach ($configs as $existingConfig) {
            if (is_array($existingConfig) && ($existingConfig['name'] ?? null) === $configName) {
                return;
            }
        }

        $configs[] = [
            'name' => $configName,
            'signing_secret' => '',
            'signature_header_name' => 'x-signature',
            'signature_validator' => Webhooks\CheckoutSpatieSignatureValidator::class,
            'webhook_profile' => Webhooks\CheckoutWebhookProfile::class,
            'webhook_response' => Webhooks\CheckoutWebhookResponse::class,
            'webhook_model' => WebhookCall::class,
            'store_headers' => [
                'x-signature',
                'stripe-signature',
            ],
            'process_webhook_job' => Webhooks\ProcessCheckoutWebhook::class,
        ];

        config([
            'webhook-client.configs' => $configs,
        ]);
    }

    protected function registerSpatieWebhookClient(): void
    {
        if (! class_exists(WebhookClientServiceProvider::class)) {
            return;
        }

        if (method_exists($this->app, 'getProvider') && $this->app->getProvider(WebhookClientServiceProvider::class) instanceof WebhookClientServiceProvider) {
            return;
        }

        $this->app->register(WebhookClientServiceProvider::class);
    }

    protected function validatePaymentGatewayConfiguration(): void
    {
        $stepEnabled = config('checkout.steps.enabled.process_payment', true);

        if (! $stepEnabled) {
            return;
        }

        $hasCashier = class_exists(GatewayManager::class);
        $hasCashierChip = class_exists(Cashier::class);
        $hasChip = class_exists(Chip::class);

        if (! $hasCashier && ! $hasCashierChip && ! $hasChip) {
            throw MissingPaymentGatewayException::noGatewayInstalled();
        }
    }

    protected function registerChipIntegration(): void
    {
        $registrar = new ChipIntegrationRegistrar;
        $registrar->register();
    }
}
