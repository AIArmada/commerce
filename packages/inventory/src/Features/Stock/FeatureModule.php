<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock;

use AIArmada\Inventory\Features\Stock\Actions\RestoreOrderStock as RestoreOrderStockAction;
use AIArmada\Inventory\Features\Stock\Api\AllocationCandidates;
use AIArmada\Inventory\Features\Stock\Api\DeductionCandidates;
use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Stock\Api\ReservationStock;
use AIArmada\Inventory\Features\Stock\Api\RestoreOrderStock;
use AIArmada\Inventory\Features\Stock\Api\StockBalances;
use AIArmada\Inventory\Features\Stock\Api\StockHealth;
use AIArmada\Inventory\Features\Stock\Api\StockIdentity;
use AIArmada\Inventory\Features\Stock\Persistence\ReservationStockStore;
use AIArmada\Inventory\Features\Stock\Queries\AllocationCandidatesQuery;
use AIArmada\Inventory\Features\Stock\Queries\DeductionCandidatesQuery;
use AIArmada\Inventory\Features\Stock\Queries\FulfillmentLocationService;
use AIArmada\Inventory\Features\Stock\Queries\LocationVisibilityQuery;
use AIArmada\Inventory\Features\Stock\Queries\StockBalancesQuery;
use AIArmada\Inventory\Features\Stock\Queries\StockHealthQuery;
use AIArmada\Inventory\Features\Stock\Queries\StockIdentityQuery;
use AIArmada\Inventory\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(FulfillmentLocationService::class);
        $app->singleton(AllocationCandidates::class, AllocationCandidatesQuery::class);
        $app->singleton(DeductionCandidates::class, DeductionCandidatesQuery::class);
        $app->singleton(LocationVisibility::class, LocationVisibilityQuery::class);
        $app->singleton(ReservationStock::class, ReservationStockStore::class);
        $app->singleton(RestoreOrderStock::class, RestoreOrderStockAction::class);
        $app->singleton(StockBalances::class, StockBalancesQuery::class);
        $app->singleton(StockHealth::class, StockHealthQuery::class);
        $app->singleton(StockIdentity::class, StockIdentityQuery::class);
    }

    public function boot(Application $app): void {}
}
