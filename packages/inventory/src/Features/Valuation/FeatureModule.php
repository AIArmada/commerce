<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation;

use AIArmada\Inventory\Features\Valuation\Api\InventoryValues;
use AIArmada\Inventory\Features\Valuation\Api\ValuationExportRows;
use AIArmada\Inventory\Features\Valuation\Persistence\FifoCostService;
use AIArmada\Inventory\Features\Valuation\Persistence\StandardCostService;
use AIArmada\Inventory\Features\Valuation\Persistence\ValuationService;
use AIArmada\Inventory\Features\Valuation\Persistence\WeightedAverageCostService;
use AIArmada\Inventory\Features\Valuation\Queries\InventoryValuesQuery;
use AIArmada\Inventory\Features\Valuation\Queries\ValuationExportRowsQuery;
use AIArmada\Inventory\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(InventoryValues::class, InventoryValuesQuery::class);
        $app->singleton(ValuationExportRows::class, ValuationExportRowsQuery::class);
        $app->singleton(FifoCostService::class);
        $app->singleton(StandardCostService::class);
        $app->singleton(ValuationService::class);
        $app->singleton(WeightedAverageCostService::class);
    }

    public function boot(Application $app): void {}
}
