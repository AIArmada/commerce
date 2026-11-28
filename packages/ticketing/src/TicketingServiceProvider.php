<?php

declare(strict_types=1);

namespace AIArmada\Ticketing;

use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;
use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Platform\ExtensionRegistry;
use AIArmada\Ticketing\Platform\FeatureCatalog;
use AIArmada\Ticketing\Platform\FeatureModule;
use AIArmada\Ticketing\Services\DefaultPassDeliveryService;
use AIArmada\Ticketing\Services\NullPassDeliveryService;
use AIArmada\Ticketing\Support\TicketableTypeRegistry;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class TicketingServiceProvider extends PackageServiceProvider
{
    /** @var list<FeatureModule> */
    private array $modules = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('ticketing')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PassDeliveryServiceInterface::class, function ($app) {
            return config('ticketing.notifications.ticket.enabled', true)
                ? $app->make(DefaultPassDeliveryService::class)
                : $app->make(NullPassDeliveryService::class);
        });
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule');

            if (! $module instanceof FeatureModule) {
                throw new RuntimeException('Invalid ticketing feature module: ' . $feature);
            }

            $module->register($this->app);
            $this->modules[] = $module;
        }
    }

    public function packageBooted(): void
    {
        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            PassIssuerInterface::class,
            PassDeliveryServiceInterface::class,
            PassTransferServiceInterface::class,
            TicketableTypeRegistry::class,
        ];
    }
}
