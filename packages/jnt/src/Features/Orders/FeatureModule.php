<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders;

use AIArmada\Jnt\Features\Orders\Actions\CancelStoredJntOrder;
use AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder;
use AIArmada\Jnt\Features\Orders\Api\DashboardStats;
use AIArmada\Jnt\Features\Orders\Queries\JntStatsAggregator;
use AIArmada\Jnt\Features\Orders\Api\ReadWaybillOrder;
use AIArmada\Jnt\Features\Orders\Queries\FindWaybillOrder;
use AIArmada\Jnt\Features\Orders\Api\UpdateTrackingSnapshot;
use AIArmada\Jnt\Features\Orders\Actions\WriteTrackingSnapshot;
use AIArmada\Jnt\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(\AIArmada\Jnt\Features\Orders\Api\SyncStoredTracking::class, \AIArmada\Jnt\Features\Orders\Actions\SyncStoredOrderTracking::class);
        $app->bind(\AIArmada\Jnt\Features\Orders\Api\ReadWebhookOrder::class, \AIArmada\Jnt\Features\Orders\Queries\FindWebhookOrder::class);
        $app->bind(\AIArmada\Jnt\Features\Orders\Api\ReadTrackingOrderOwner::class, \AIArmada\Jnt\Features\Orders\Queries\FindTrackingOrderOwner::class);
        $app->bind(CancelStoredOrder::class, CancelStoredJntOrder::class);
        $app->bind(DashboardStats::class, JntStatsAggregator::class);
        $app->bind(\AIArmada\Jnt\Features\Orders\Api\ReadNotificationOrder::class, \AIArmada\Jnt\Features\Orders\Queries\FindNotificationOrder::class);
        $app->bind(ReadWaybillOrder::class, FindWaybillOrder::class);
        $app->bind(UpdateTrackingSnapshot::class, WriteTrackingSnapshot::class);
    }

    public function boot(Application $app): void {}
}
