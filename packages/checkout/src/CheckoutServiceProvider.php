<?php

declare(strict_types=1);

namespace AIArmada\Checkout;

use AIArmada\Cashier\GatewayManager;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\StepContributor;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Exceptions\MissingPaymentGatewayException;
use AIArmada\Checkout\Listeners\RedeemVouchersOnCheckoutCompleted;
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
use AIArmada\Checkout\Support\RegisterTaggedPaymentProcessors;
use AIArmada\Chip\Facades\Chip;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use AIArmada\Vouchers\VoucherServiceProvider;
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
        $this->configureSpatieWebhookClient();

        $this->validateConfiguration('checkout', [
            'defaults.currency',
        ]);

        $this->validateStepConfiguration();
        $this->validateOwnerConfiguration();
        $this->validateCallbackTokenConfiguration();
        $this->validatePaymentGatewayConfiguration();
        $this->registerDefaultSteps();
        $this->registerOptionalIntegrations();

        // Defer optional payment discovery and the step freeze until after all
        // providers have booted. This lets provider packages register tagged
        // processors regardless of service-provider load order.
        $this->app->booted(function (): void {
            $resolver = $this->app->make(PaymentGatewayResolver::class);
            app(RegisterTaggedPaymentProcessors::class)->register($resolver);
            $this->freezeStepRegistry();
        });
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
            $resolver = new PaymentGatewayResolver;

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
            finalizer: $app->make(CheckoutFinalizer::class),
            paymentResolver: $app->make(PaymentGatewayResolverInterface::class),
        ));

        $this->app->alias(CheckoutService::class, CheckoutServiceInterface::class);
        $this->app->alias(CheckoutService::class, 'checkout');
    }

    protected function registerDefaultSteps(): void
    {
        $registry = $this->app->make(CheckoutStepRegistry::class);

        $registry->registerLazy('validate_cart', fn () => $this->app->make(ValidateCartStep::class));
        $registry->registerLazy('resolve_customer', fn () => $this->app->make(ResolveCustomerStep::class));
        $registry->registerLazy('calculate_pricing', fn () => $this->app->make(CalculatePricingStep::class));
        $registry->registerLazy('calculate_shipping', fn () => $this->app->make(CalculateShippingStep::class));
        $registry->registerLazy('process_payment', fn () => $this->app->make(ProcessPaymentStep::class));
        $registry->registerLazy('persist_customer', fn () => $this->app->make(PersistCustomerStep::class));
        $registry->registerLazy('create_order', fn () => $this->app->make(CreateOrderStep::class));
        $registry->registerLazy('dispatch_documents', fn () => $this->app->make(DispatchDocumentGenerationStep::class));
    }

    protected function registerOptionalIntegrations(): void
    {
        $registry = $this->app->make(CheckoutStepRegistry::class);

        app(RegisterCheckoutOptionalSteps::class)->register($registry);

        $this->registerChipIntegration();
        $this->registerVoucherIntegration();

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

        $configs = config('webhook-client.configs', []);

        if (! is_array($configs)) {
            $configs = [];
        }

        $webhookRoutes = config('checkout.routes.webhooks', []);

        if (! is_array($webhookRoutes) || $webhookRoutes === []) {
            $webhookRoutes = [
                'chip' => [
                    'path' => 'chip',
                    'config' => 'checkout.webhook.chip',
                    'gateways' => ['chip', 'cashier-chip'],
                ],
                'stripe' => [
                    'path' => 'stripe',
                    'config' => 'checkout.webhook.stripe',
                    'gateways' => ['cashier'],
                ],
            ];
        }
        $checkoutConfigNames = ['checkout.webhook'];

        foreach ($webhookRoutes as $webhookRoute) {
            if (is_array($webhookRoute) && is_string($webhookRoute['config'] ?? null)) {
                $checkoutConfigNames[] = $webhookRoute['config'];
            }
        }

        $configs = array_values(array_filter($configs, static function (mixed $existingConfig) use ($checkoutConfigNames): bool {
            if (! is_array($existingConfig)) {
                return false;
            }

            if (in_array($existingConfig['name'] ?? null, $checkoutConfigNames, true)) {
                return false;
            }

            $processWebhookJob = $existingConfig['process_webhook_job'] ?? null;

            return is_string($processWebhookJob) && $processWebhookJob !== '';
        }));

        foreach ($webhookRoutes as $gateway => $webhookRoute) {
            if (! is_array($webhookRoute)) {
                continue;
            }

            $configName = $webhookRoute['config'] ?? null;

            if (! is_string($configName) || $configName === '') {
                continue;
            }

            $configs[] = [
                'name' => $configName,
                'signing_secret' => '',
                'signature_header_name' => $gateway === 'stripe' ? 'stripe-signature' : 'x-signature',
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
        }

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

        if ($hasCashier && app()->environment('production') && ! filled(config('checkout.webhooks.stripe.secret'))) {
            throw new RuntimeException(
                'Checkout Stripe webhook verification requires CHECKOUT_STRIPE_WEBHOOK_SECRET in production.',
            );
        }
    }

    protected function validateCallbackTokenConfiguration(): void
    {
        $ttl = (int) config('checkout.payment.callback_token_ttl', 0);
        $maxAttempts = (int) config('checkout.payment.callback_rate_limit.max_attempts', 0);
        $decaySeconds = (int) config('checkout.payment.callback_rate_limit.decay_seconds', 0);

        if ($ttl < 1 || $ttl > 60 * 60 * 24) {
            throw new RuntimeException('Checkout callback token TTL must be between 1 second and 24 hours.');
        }

        if ($maxAttempts < 1 || $decaySeconds < 1) {
            throw new RuntimeException('Checkout callback rate limiting must define positive attempts and decay values.');
        }
    }

    protected function registerChipIntegration(): void
    {
        $registrar = new ChipIntegrationRegistrar;
        $registrar->register();
    }

    protected function registerVoucherIntegration(): void
    {
        if (! class_exists(VoucherServiceProvider::class)) {
            return;
        }

        $this->app->make(Dispatcher::class)
            ->listen(CheckoutCompleted::class, RedeemVouchersOnCheckoutCompleted::class);
    }

    protected function freezeStepRegistry(): void
    {
        $registry = $this->app->make(CheckoutStepRegistry::class);

        foreach ($this->app->tagged('checkout.steps') as $contributor) {
            if ($contributor instanceof StepContributor) {
                foreach ($contributor->steps() as $identifier => $factory) {
                    $registry->registerLazy($identifier, $factory);
                }
            }
        }

        $registry->freeze();
    }
}
