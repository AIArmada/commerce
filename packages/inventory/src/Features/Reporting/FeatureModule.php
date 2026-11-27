<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting;

use AIArmada\Inventory\Features\Reporting\Queries\StockLevelReport;
use AIArmada\Inventory\Features\Reporting\Queries\InventoryKpiService;
use AIArmada\Inventory\Features\Reporting\Queries\InventoryReconciliationReport;
use AIArmada\Inventory\Features\Reporting\Queries\MovementAnalysisReport;
use AIArmada\Inventory\Features\Reporting\Actions\ExportService;
use AIArmada\Inventory\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(ExportService::class);
        $app->singleton(StockLevelReport::class);
        $app->singleton(InventoryKpiService::class);
        $app->singleton(InventoryReconciliationReport::class);
        $app->singleton(MovementAnalysisReport::class);
    }

    public function boot(Application $app): void {}
}
