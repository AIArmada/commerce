<?php

declare(strict_types=1);

namespace AIArmada\Chip;

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\Commands\ChipHealthCheckCommand;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ChipServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('chip')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommand(ChipHealthCheckCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->registerServices();
        $this->registerClients();
    }

    public function packageBooted(): void
    {
        $this->validateConfiguration('chip', [
            'collect.api_key',
            'collect.brand_id',
        ]);
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            ChipCollectService::class,
            ChipSendService::class,
            WebhookService::class,
            ChipCollectClient::class,
            ChipSendClient::class,
            'chip.collect',
            'chip.send',
        ];
    }

    protected function registerServices(): void
    {
        $this->app->singleton(function ($app): ChipCollectService {
            return new ChipCollectService(
                $app->make(ChipCollectClient::class),
                $app->make(CacheRepository::class)
            );
        });

        $this->app->singleton(function ($app): ChipSendService {
            return new ChipSendService(
                $app->make(ChipSendClient::class)
            );
        });

        $this->app->singleton(function ($app): WebhookService {
            return new WebhookService;
        });

        $this->app->alias(ChipCollectService::class, 'chip.collect');
        $this->app->alias(ChipSendService::class, 'chip.send');
        $this->app->alias(WebhookService::class, 'chip.webhook');
    }

    protected function registerClients(): void
    {
        $this->app->singleton(function (): ChipCollectClient {
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

        $this->app->singleton(function (): ChipSendClient {
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
}
