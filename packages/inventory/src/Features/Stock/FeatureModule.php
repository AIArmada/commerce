<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock;

use AIArmada\Inventory\Features\Stock\Api\StockHealth;
use AIArmada\Inventory\Features\Stock\Queries\FulfillmentLocationService;
use AIArmada\Inventory\Features\Stock\Queries\StockHealthQuery;
use AIArmada\Inventory\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(FulfillmentLocationService::class);
        $app->singleton(StockHealth::class, StockHealthQuery::class);
    }

    public function boot(Application $app): void {}
}
