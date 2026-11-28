<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock;

use AIArmada\Inventory\Features\Stock\Actions\RestoreOrderStock as RestoreOrderStockAction;
use AIArmada\Inventory\Features\Stock\Api\AllocationCandidates;
use AIArmada\Inventory\Features\Stock\Api\DeductionCandidates;
use AIArmada\Inventory\Features\Stock\Api\LocationOwnerBatch;
use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Stock\Api\MovementReferences;
use AIArmada\Inventory\Features\Stock\Api\ReorderStock;
use AIArmada\Inventory\Features\Stock\Api\ReservationStock;
use AIArmada\Inventory\Features\Stock\Api\RestoreOrderStock;
use AIArmada\Inventory\Features\Stock\Api\StockAvailability;
use AIArmada\Inventory\Features\Stock\Api\StockExportRows;
use AIArmada\Inventory\Features\Stock\Api\StockBalances;
use AIArmada\Inventory\Features\Stock\Api\StockHealth;
use AIArmada\Inventory\Features\Stock\Api\StockIdentity;
use AIArmada\Inventory\Features\Stock\Api\StockKpiMetrics;
use AIArmada\Inventory\Features\Stock\Persistence\ReservationStockStore;
use AIArmada\Inventory\Features\Stock\Queries\AllocationCandidatesQuery;
use AIArmada\Inventory\Features\Stock\Queries\DeductionCandidatesQuery;
use AIArmada\Inventory\Features\Stock\Queries\FulfillmentLocationService;
use AIArmada\Inventory\Features\Stock\Queries\InventoryStockQuery;
use AIArmada\Inventory\Features\Stock\Queries\LocationOwnerBatchQuery;
use AIArmada\Inventory\Features\Stock\Queries\LocationVisibilityQuery;
use AIArmada\Inventory\Features\Stock\Queries\MovementAnalysisReport;
use AIArmada\Inventory\Features\Stock\Queries\MovementReferencesQuery;
use AIArmada\Inventory\Features\Stock\Queries\ReorderStockQuery;
use AIArmada\Inventory\Features\Stock\Queries\StockAvailabilityQuery;
use AIArmada\Inventory\Features\Stock\Queries\StockExportRowsQuery;
use AIArmada\Inventory\Features\Stock\Queries\StockBalancesQuery;
use AIArmada\Inventory\Features\Stock\Queries\StockHealthQuery;
use AIArmada\Inventory\Features\Stock\Queries\StockIdentityQuery;
use AIArmada\Inventory\Features\Stock\Queries\StockKpiMetricsQuery;
use AIArmada\Inventory\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(InventoryService::class);
        $app->alias(InventoryService::class, 'inventory');
        $app->singleton(InventoryStockQuery::class);
        $app->singleton(FulfillmentLocationService::class);
        $app->singleton(MovementAnalysisReport::class);
        $app->singleton(AllocationCandidates::class, AllocationCandidatesQuery::class);
        $app->singleton(DeductionCandidates::class, DeductionCandidatesQuery::class);
        $app->singleton(LocationOwnerBatch::class, LocationOwnerBatchQuery::class);
        $app->singleton(LocationVisibility::class, LocationVisibilityQuery::class);
        $app->singleton(MovementReferences::class, MovementReferencesQuery::class);
        $app->singleton(ReorderStock::class, ReorderStockQuery::class);
        $app->singleton(ReservationStock::class, ReservationStockStore::class);
        $app->singleton(RestoreOrderStock::class, RestoreOrderStockAction::class);
        $app->singleton(StockAvailability::class, StockAvailabilityQuery::class);
        $app->singleton(StockExportRows::class, StockExportRowsQuery::class);
        $app->singleton(StockBalances::class, StockBalancesQuery::class);
        $app->singleton(StockHealth::class, StockHealthQuery::class);
        $app->singleton(StockIdentity::class, StockIdentityQuery::class);
        $app->singleton(StockKpiMetrics::class, StockKpiMetricsQuery::class);
    }

    public function boot(Application $app): void {}
}
