<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Reporting;

use AIArmada\Jnt\Features\Reporting\Api\DashboardStats;
use AIArmada\Jnt\Features\Reporting\Queries\JntStatsAggregator;
use AIArmada\Jnt\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DashboardStats::class, JntStatsAggregator::class);
    }

    public function boot(Application $app): void {}
}
