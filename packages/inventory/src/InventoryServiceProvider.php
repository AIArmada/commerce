<?php

declare(strict_types=1);

namespace AIArmada\Inventory;

use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Contracts\ScopedCapacityReservationServiceInterface;
use AIArmada\Inventory\Features\Backorders\Persistence\BackorderService;
use AIArmada\Inventory\Features\Replenishment\Persistence\DemandForecastService;
use AIArmada\Inventory\Features\Replenishment\Persistence\ReplenishmentService;
use AIArmada\Inventory\Features\Reporting\Actions\ExportService;
use AIArmada\Inventory\Features\Reporting\Queries\InventoryKpiService;
use AIArmada\Inventory\Features\Reporting\Queries\InventoryReconciliationReport;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Console\CleanupExpiredAllocationsCommand;
use AIArmada\Inventory\Features\Reservations\Persistence\CheckoutReservationService;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Stock\Queries\FulfillmentLocationService;
use AIArmada\Inventory\Features\Stock\Queries\MovementAnalysisReport;
use AIArmada\Inventory\Features\Stock\Queries\StockLevelReport;
use AIArmada\Inventory\Features\Stock\Services\InventoryService;
use AIArmada\Inventory\Features\Traceability\Persistence\BatchService;
use AIArmada\Inventory\Features\Traceability\Persistence\SerialService;
use AIArmada\Inventory\Features\Traceability\Queries\SerialLookupService;
use AIArmada\Inventory\Features\Valuation\EntryPoints\Console\CreateValuationSnapshotCommand;
use AIArmada\Inventory\Features\Valuation\Persistence\FifoCostService;
use AIArmada\Inventory\Features\Valuation\Persistence\StandardCostService;
use AIArmada\Inventory\Features\Valuation\Persistence\ValuationService;
use AIArmada\Inventory\Features\Valuation\Persistence\WeightedAverageCostService;
use AIArmada\Inventory\Platform\ExtensionRegistry;
use AIArmada\Inventory\Platform\FeatureCatalog;
use AIArmada\Inventory\Platform\FeatureModule;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class InventoryServiceProvider extends PackageServiceProvider
{
    /** @var list<FeatureModule> */
    private array $modules = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('inventory')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                CleanupExpiredAllocationsCommand::class,
                CreateValuationSnapshotCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->registerFeatureModules();
    }

    public function packageBooted(): void
    {
        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            InventoryService::class,
            InventoryAllocationService::class,
            BatchService::class,
            SerialService::class,
            SerialLookupService::class,
            FifoCostService::class,
            WeightedAverageCostService::class,
            StandardCostService::class,
            ValuationService::class,
            BackorderService::class,
            DemandForecastService::class,
            ReplenishmentService::class,
            InventoryKpiService::class,
            InventoryReconciliationReport::class,
            MovementAnalysisReport::class,
            StockLevelReport::class,
            ExportService::class,
            FulfillmentLocationService::class,
            CheckoutReservationServiceInterface::class,
            CheckoutReservationService::class,
            ScopedCapacityReservationServiceInterface::class,
            'inventory',
            'inventory.allocations',
        ];
    }

    private function registerFeatureModules(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule');

            if (! $module instanceof FeatureModule) {
                throw new RuntimeException('Invalid inventory feature module: ' . $feature);
            }

            $module->register($this->app);
            $this->modules[] = $module;
        }
    }
}
