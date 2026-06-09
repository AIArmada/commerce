<?php

declare(strict_types=1);

namespace AIArmada\Chip;

use AIArmada\Chip\Actions\DispatchChipWebhookAction;
use AIArmada\Chip\Actions\HandleSendInstructionWebhookAction;
use AIArmada\Chip\Actions\RunChipPurchaseDocGenerationAction;
use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\Commands\ChipHealthCheckCommand;
use AIArmada\Chip\Commands\CleanWebhooksCommand;
use AIArmada\Chip\Commands\RetryWebhooksCommand;
use AIArmada\Chip\Commands\SyncChipRecordsFromApiCommand;
use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Chip\Listeners\LinkChipCustomerFromCheckoutCompletion;
use AIArmada\Chip\Listeners\StoreWebhookData;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\ChipCustomerDirectory;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\Chip\Services\WebhookEventDispatcher;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\Chip\Support\BuildChipDocData;
use AIArmada\Chip\Support\ChipCustomerBridge;
use AIArmada\Chip\Support\DocsIntegrationRegistrar;
use AIArmada\Chip\Support\WebhookOwnerBatchRunner;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookClientServiceProvider;

final class ChipServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    private const CUSTOMER_MODEL = 'AIArmada\\Customers\\Models\\Customer';

    private const CHECKOUT_COMPLETED_EVENT = 'AIArmada\\Checkout\\Events\\CheckoutCompleted';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('chip')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                ChipHealthCheckCommand::class,
                RetryWebhooksCommand::class,
                CleanWebhooksCommand::class,
                SyncChipRecordsFromApiCommand::class,
            ]);
    }

    public function configureWebhookRoutes(): void
    {
        if (! config('chip.webhooks.enabled', true)) {
            return;
        }

        Route::middleware(config('chip.webhooks.middleware', ['api']))
            ->group(fn () => $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php'));
    }

    public function packageRegistered(): void
    {
        $this->configureSpatieWebhookClient();
        $this->registerSpatieWebhookClient();
        $this->registerServices();
        $this->registerClients();
        $this->registerGateway();
        $this->registerMiddleware();
        $this->registerActions();
        $this->registerSupport();
    }

    protected function configureSpatieWebhookClient(): void
    {
        if (! config('chip.webhooks.enabled', true)) {
            return;
        }

        if (! class_exists(WebhookCall::class)) {
            return;
        }

        $configName = 'chip.webhook';
        $configs = config('webhook-client.configs', []);

        if (! is_array($configs)) {
            $configs = [];
        }

        foreach ($configs as $existingConfig) {
            if (is_array($existingConfig) && ($existingConfig['name'] ?? null) === $configName) {
                return;
            }
        }

        $configs[] = [
            'name' => $configName,
            'signing_secret' => '',
            'signature_header_name' => 'x-signature',
            'signature_validator' => Webhooks\ChipSpatieSignatureValidator::class,
            'webhook_profile' => Webhooks\ChipWebhookProfile::class,
            'webhook_response' => Webhooks\ChipWebhookResponse::class,
            'webhook_model' => WebhookCall::class,
            'store_headers' => [
                'x-signature',
            ],
            'process_webhook_job' => Webhooks\ProcessChipWebhook::class,
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

    public function packageBooted(): void
    {
        $this->registerMorphAliases();

        $this->validateConfiguration('chip', [
            'collect.api_key',
            'collect.brand_id',
        ]);

        $this->validateWebhookBrandIdMap();
        $this->configureWebhookRoutes();
        $this->registerEventListeners();
        $this->bootDocsIntegration();
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            ChipCollectService::class,
            ChipCustomerDirectory::class,
            ChipCustomerDirectoryInterface::class,
            ChipSendService::class,
            WebhookService::class,
            ChipCollectClient::class,
            ChipSendClient::class,
            ChipGateway::class,
            PaymentGatewayInterface::class,
            'chip.collect',
            'chip.send',
            'chip.gateway',
        ];
    }

    protected function bootDocsIntegration(): void
    {
        /** @var DocsIntegrationRegistrar $registrar */
        $registrar = $this->app->make(DocsIntegrationRegistrar::class);
        $registrar->register();
    }

    protected function registerMiddleware(): void
    {
        $this->app->singleton(VerifyWebhookSignature::class, function ($app): VerifyWebhookSignature {
            return new VerifyWebhookSignature(
                $app->make(WebhookService::class)
            );
        });
    }

    protected function registerEventListeners(): void
    {
        Event::listen(WebhookReceived::class, StoreWebhookData::class);
        $this->registerCheckoutCustomerBridgeListener();
    }

    private function registerCheckoutCustomerBridgeListener(): void
    {
        if (! class_exists(self::CHECKOUT_COMPLETED_EVENT)) {
            return;
        }

        Event::listen(self::CHECKOUT_COMPLETED_EVENT, LinkChipCustomerFromCheckoutCompletion::class);
    }

    protected function registerMorphAliases(): void
    {
        $customerModel = config('chip.integrations.customer_bridge.customer_model', self::CUSTOMER_MODEL);
        $customerMorphAlias = config('chip.integrations.customer_bridge.customer_morph_alias', 'Customer');

        if (! is_string($customerModel) || $customerModel === '' || ! class_exists($customerModel)) {
            return;
        }

        if (! is_string($customerMorphAlias) || $customerMorphAlias === '') {
            return;
        }

        Relation::morphMap([
            $customerMorphAlias => $customerModel,
        ]);
    }

    protected function registerServices(): void
    {
        $this->app->singleton(ChipCollectService::class, function ($app): ChipCollectService {
            return new ChipCollectService(
                $app->make(ChipCollectClient::class),
                $app->make(CacheRepository::class)
            );
        });

        $this->app->singleton(ChipCustomerDirectory::class, function ($app): ChipCustomerDirectory {
            return new ChipCustomerDirectory(
                $app->make(ChipCollectService::class)
            );
        });

        $this->app->alias(ChipCustomerDirectory::class, ChipCustomerDirectoryInterface::class);

        $this->app->singleton(ChipSendService::class, function ($app): ChipSendService {
            return new ChipSendService(
                $app->make(ChipSendClient::class)
            );
        });

        $this->app->singleton(WebhookService::class, function ($app): WebhookService {
            return new WebhookService;
        });

        $this->app->singleton(WebhookEventDispatcher::class);

        $this->app->alias(ChipCollectService::class, 'chip.collect');
        $this->app->alias(ChipSendService::class, 'chip.send');
        $this->app->alias(WebhookService::class, 'chip.webhook');
    }

    protected function registerActions(): void
    {
        $this->app->singleton(DispatchChipWebhookAction::class);
        $this->app->singleton(HandleSendInstructionWebhookAction::class);
        $this->app->singleton(RunChipPurchaseDocGenerationAction::class);
        $this->app->singleton(BuildChipDocData::class);
    }

    protected function registerSupport(): void
    {
        $this->app->singleton(ChipCustomerBridge::class);
        $this->app->singleton(WebhookOwnerBatchRunner::class);
        $this->app->singleton(DocsIntegrationRegistrar::class);
    }

    protected function registerClients(): void
    {
        $this->app->singleton(ChipCollectClient::class, function (): ChipCollectClient {
            $apiKey = config('chip.collect.api_key');
            $brandId = config('chip.collect.brand_id');

            $baseUrlConfig = config('chip.collect.base_url', 'https://gate.chip-in.asia/api/v1/');
            $environment = config('chip.environment', 'sandbox');

            if (is_array($baseUrlConfig)) {
                $baseUrl = $baseUrlConfig[$environment] ?? reset($baseUrlConfig);
            } else {
                $baseUrl = $baseUrlConfig;
            }

            return new ChipCollectClient(
                $apiKey,
                $brandId,
                (string) $baseUrl,
                config('chip.http.timeout', 30),
                config('chip.http.retry', [
                    'attempts' => 3,
                    'delay' => 1000,
                ])
            );
        });

        $this->app->singleton(ChipSendClient::class, function (): ChipSendClient {
            $apiKey = config('chip.send.api_key');
            $apiSecret = config('chip.send.api_secret');

            $environment = config('chip.environment', 'sandbox');

            return new ChipSendClient(
                apiKey: $apiKey,
                apiSecret: $apiSecret,
                environment: $environment,
                baseUrl: config("chip.send.base_url.{$environment}")
                ?? config('chip.send.base_url.sandbox', 'https://staging-api.chip-in.asia/api'),
                timeout: config('chip.http.timeout', 30),
                retryConfig: config('chip.http.retry', [
                    'attempts' => 3,
                    'delay' => 1000,
                ])
            );
        });
    }

    protected function registerGateway(): void
    {
        $this->app->singleton(ChipGateway::class, function ($app): ChipGateway {
            return new ChipGateway(
                $app->make(ChipCollectService::class),
                $app->make(WebhookService::class)
            );
        });

        if (! $this->app->bound(PaymentGatewayInterface::class)) {
            $this->app->bind(PaymentGatewayInterface::class, ChipGateway::class);
        }

        $this->app->alias(ChipGateway::class, 'chip.gateway');
    }

    protected function validateWebhookBrandIdMap(): void
    {
        if (! config('chip.owner.enabled', false)) {
            return;
        }

        $map = config('chip.owner.webhook_brand_id_map', []);

        if (! is_array($map)) {
            throw new InvalidArgumentException(
                'Configuration error: "chip.owner.webhook_brand_id_map" must be an array.'
            );
        }

        foreach ($map as $brandId => $entry) {
            if (! is_array($entry)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Configuration error: "chip.owner.webhook_brand_id_map[%s]" must be an array with "owner_type" and "owner_id" keys.',
                        $brandId
                    )
                );
            }

            $ownerType = $entry['owner_type'] ?? $entry['type'] ?? null;
            $ownerId = $entry['owner_id'] ?? $entry['id'] ?? null;

            if (empty($ownerType)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Configuration error: "chip.owner.webhook_brand_id_map[%s]" must include "owner_type".',
                        $brandId
                    )
                );
            }

            if (empty($ownerId)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Configuration error: "chip.owner.webhook_brand_id_map[%s]" must include "owner_id".',
                        $brandId
                    )
                );
            }

            if (! is_string($ownerType)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Configuration error: "chip.owner.webhook_brand_id_map[%s][owner_type]" must be a string.',
                        $brandId
                    )
                );
            }

            if (! is_string($ownerId) && ! is_int($ownerId)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Configuration error: "chip.owner.webhook_brand_id_map[%s][owner_id]" must be a string or integer.',
                        $brandId
                    )
                );
            }
        }
    }
}
