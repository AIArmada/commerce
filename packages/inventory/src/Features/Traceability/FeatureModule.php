<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability;

use AIArmada\Inventory\Features\Traceability\Persistence\BatchService;
use AIArmada\Inventory\Features\Traceability\Persistence\SerialService;
use AIArmada\Inventory\Features\Traceability\Queries\SerialLookupService;
use AIArmada\Inventory\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(BatchService::class);
        $app->singleton(SerialService::class);
        $app->singleton(SerialLookupService::class);
    }

    public function boot(Application $app): void {}
}
