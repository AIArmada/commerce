<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation;

use AIArmada\Inventory\Features\Valuation\Api\CostLayerValues;
use AIArmada\Inventory\Features\Valuation\Persistence\FifoCostService;
use AIArmada\Inventory\Features\Valuation\Persistence\StandardCostService;
use AIArmada\Inventory\Features\Valuation\Persistence\ValuationService;
use AIArmada\Inventory\Features\Valuation\Persistence\WeightedAverageCostService;
use AIArmada\Inventory\Features\Valuation\Queries\CostLayerValuesQuery;
use AIArmada\Inventory\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(CostLayerValues::class, CostLayerValuesQuery::class);
        $app->singleton(FifoCostService::class);
        $app->singleton(StandardCostService::class);
        $app->singleton(ValuationService::class);
        $app->singleton(WeightedAverageCostService::class);
    }

    public function boot(Application $app): void {}
}
