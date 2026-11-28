<?php

declare(strict_types=1);

namespace AIArmada\Jnt;

use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\Jnt\Contracts\CarrierTransportFactory;
use AIArmada\Jnt\Contracts\StatusMappingStrategyInterface;
use AIArmada\Jnt\Contracts\TrackingReader;
use AIArmada\Jnt\Contracts\WaybillWriter;
use AIArmada\Jnt\Drivers\HttpCarrierTransportFactory;
use AIArmada\Jnt\Drivers\JntShippingDriver;
use AIArmada\Jnt\Drivers\LocalWaybillWriter;
use AIArmada\Jnt\EntryPoints\Cart\JntShippingCalculator;
use AIArmada\Jnt\EntryPoints\Commands\Health\HealthCheckCommand;
use AIArmada\Jnt\EntryPoints\Commands\Orders\OrderCancelCommand;
use AIArmada\Jnt\EntryPoints\Commands\Orders\OrderCreateCommand;
use AIArmada\Jnt\EntryPoints\Commands\Orders\OrderPrintCommand;
use AIArmada\Jnt\EntryPoints\Commands\Tracking\OrderTrackCommand;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Features\Notifications\EntryPoints\Listeners\SendShipmentNotifications;
use AIArmada\Jnt\Features\Tracking\Actions\JntTrackingService;
use AIArmada\Jnt\Features\Tracking\Api\ReadTrackingStatus;
use AIArmada\Jnt\Features\Tracking\Api\TrackingPollCache;
use AIArmada\Jnt\Features\Tracking\EntryPoints\Commands\WebhookTestCommand;
use AIArmada\Jnt\Features\Tracking\EntryPoints\Jobs\ProcessJntWebhook;
use AIArmada\Jnt\Features\Tracking\EntryPoints\Webhooks\JntSpatieSignatureValidator;
use AIArmada\Jnt\Features\Tracking\EntryPoints\Webhooks\JntWebhookProfile;
use AIArmada\Jnt\Features\Tracking\EntryPoints\Webhooks\JntWebhookResponse;
use AIArmada\Jnt\Features\Tracking\Support\JntStatusMapper;
use AIArmada\Jnt\Features\Tracking\Support\WebhookService;
use AIArmada\Jnt\Platform\ExtensionRegistry;
use AIArmada\Jnt\Platform\FeatureCatalog;
use AIArmada\Jnt\Platform\FeatureModule;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Support\StatusMappingStrategyRegistry;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookClientServiceProvider;

/**
 * J&T Express Service Provider
 *
 * Bootstraps the J&T Express package for Laravel integration using Spatie's package tools.
 * Handles service registration, configuration publishing, command registration,
 * webhook setup, and configuration validation.
 */
class JntServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('jnt')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                HealthCheckCommand::class,
                OrderCreateCommand::class,
                OrderTrackCommand::class,
                OrderCancelCommand::class,
                OrderPrintCommand::class,
                WebhookTestCommand::class,
            ]);
    }

    /**
     * Register package services.
     */
    public function registeringPackage(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');

            if ($module instanceof FeatureModule) {
                $module->register($this->app);
            }
        }

        $this->configureSpatieWebhookClient();
        $this->registerSpatieWebhookClient();
        $this->registerServices();
        $this->registerOptionalIntegrations();
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

    /**
     * Bootstrap package services.
     */
    public function bootingPackage(): void
    {
        foreach (array_keys(FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__)['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');

            if ($module instanceof FeatureModule) {
                $module->boot($this->app);
            }
        }

        $this->loadRoutesFrom(__DIR__ . '/Features/Tracking/EntryPoints/Routes/webhooks.php');
        $this->loadRoutesFrom(__DIR__ . '/Features/Waybills/EntryPoints/Routes/web.php');
        $this->assertOwnerResolverConfigured();
        $this->registerCartConditionProvider();
        $this->registerShippingDriver();
        $this->registerEventListeners();
    }

    protected function assertOwnerResolverConfigured(): void
    {
        if (! (bool) config('jnt.owner.enabled', false)) {
            return;
        }

        if (! $this->app->bound(OwnerResolverInterface::class)) {
            return;
        }

        if ($this->app->make(OwnerResolverInterface::class) instanceof NullOwnerResolver) {
            throw new RuntimeException('jnt.owner.enabled requires a real OwnerResolverInterface binding; NullOwnerResolver cannot scope tenants.');
        }
    }

    protected function configureSpatieWebhookClient(): void
    {
        if (! config('jnt.webhooks.enabled', true)) {
            return;
        }

        if (! class_exists(WebhookCall::class)) {
            return;
        }

        /**
         * Spatie binds the active WebhookConfig by reading the current route name.
         * We keep the route name `jnt.webhooks.status`, so the Spatie webhook config
         * entry must use that name to resolve correctly.
         */
        $configName = 'jnt.webhooks.status';

        $configs = config('webhook-client.configs', []);

        if (! is_array($configs)) {
            $configs = [];
        }

        // Spatie ships an invalid default config (`process_webhook_job` = '') which will
        // hard-fail when the WebhookClient service provider boots. Filter out invalid
        // configs defensively.
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
            'signing_secret' => (string) config('jnt.private_key', ''),
            'signature_header_name' => 'digest',
            'signature_validator' => JntSpatieSignatureValidator::class,
            'webhook_profile' => JntWebhookProfile::class,
            'webhook_response' => JntWebhookResponse::class,
            'webhook_model' => WebhookCall::class,
            'store_headers' => [
                'digest',
            ],
            'process_webhook_job' => ProcessJntWebhook::class,
        ];

        config([
            'webhook-client.configs' => $configs,
        ]);
    }

    /**
     * Register package services in the container.
     */
    protected function registerServices(): void
    {
        // Credential-bearing services resolve fresh on every make() so Octane
        // workers and per-owner credential switching never reuse stale config.
        $this->app->bind(CarrierTransportFactory::class, HttpCarrierTransportFactory::class);
        $this->app->bind(JntExpressService::class, function (Application $app): JntExpressService {
            $config = $app['config']['jnt'];

            return new JntExpressService(
                transportFactory: $app->make(CarrierTransportFactory::class),
                customerCode: $config['customer_code'] ?? null,
                password: $config['password'] ?? null,
                config: $config,
                pollCache: $app->make(TrackingPollCache::class),
            );
        });

        // Register facade accessor alias
        $this->app->alias(JntExpressService::class, 'jnt-express');

        // Register webhook service
        $this->app->bind(WebhookService::class, fn (Application $app): WebhookService => new WebhookService(
            privateKey: $app['config']['jnt']['private_key'] ?? null,
        ));

        // Register status mapper service
        $this->app->singleton(JntStatusMapper::class, fn (): JntStatusMapper => new JntStatusMapper);
        $this->app->alias(JntStatusMapper::class, 'jnt.status-mapper');
        $this->app->bind(StatusMappingStrategyInterface::class, fn (Application $app): StatusMappingStrategyInterface => $app->make(JntStatusMapper::class));

        // Register status mapping strategy registry and register JNT's strategy
        $this->app->singleton(StatusMappingStrategyRegistry::class, function (): StatusMappingStrategyRegistry {
            $registry = new StatusMappingStrategyRegistry;

            $registry->register(app(JntStatusMapper::class));

            return $registry;
        });

        // Register tracking service
        $this->app->bind(JntTrackingService::class, fn (Application $app): JntTrackingService => new JntTrackingService(
            expressService: $app->make(JntExpressService::class),
            statusMapper: $app->make(JntStatusMapper::class),
        ));
        $this->app->alias(JntTrackingService::class, 'jnt.tracking');
        $this->app->bind(ReadTrackingStatus::class, fn (Application $app): ReadTrackingStatus => $app->make(JntTrackingService::class));
        $this->app->bind(TrackingReader::class, fn (Application $app): TrackingReader => $app->make(JntTrackingService::class));
        $this->app->bind(WaybillWriter::class, LocalWaybillWriter::class);
    }

    /**
     * Register Cart/Shipping optional integrations.
     */
    protected function registerOptionalIntegrations(): void
    {
        if (class_exists('AIArmada\\Cart\\CartManager')) {
            $this->app->singleton(
                JntShippingCalculator::class,
                fn (): JntShippingCalculator => new JntShippingCalculator
            );

        }

        if (class_exists(ShippingManager::class)) {
            $this->app->bind(
                JntShippingDriver::class,
                fn (Application $app): JntShippingDriver => new JntShippingDriver(
                    $app->make(JntExpressService::class),
                    $app->make(TrackingReader::class),
                )
            );
        }
    }

    protected function registerCartConditionProvider(): void
    {
        if (! class_exists(ConditionProviderRegistry::class)) {
            return;
        }

        if (! class_exists('AIArmada\\Cart\\CartManager')) {
            return;
        }

        $this->app->make(ConditionProviderRegistry::class)
            ->register(JntShippingCalculator::class);
    }

    /**
     * Register JNT as a shipping driver if shipping package is available.
     */
    protected function registerShippingDriver(): void
    {
        // Only register if the shipping package is installed
        if (! class_exists(ShippingManager::class)) {
            return;
        }

        /** @var ShippingManager $shippingManager */
        $shippingManager = $this->app->make(ShippingManager::class);

        $shippingManager->extend('jnt', fn (Application $app): JntShippingDriver => $app->make(JntShippingDriver::class));
    }

    /**
     * Register event listeners for status change notifications.
     */
    protected function registerEventListeners(): void
    {
        if (! config('jnt.notifications.enabled', true)) {
            return;
        }

        Event::listen(
            JntOrderStatusChanged::class,
            SendShipmentNotifications::class
        );
    }
}
