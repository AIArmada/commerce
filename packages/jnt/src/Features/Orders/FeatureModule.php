<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders;

use AIArmada\Jnt\Features\Orders\Actions\CancelStoredJntOrder;
use AIArmada\Jnt\Features\Orders\Actions\SyncStoredOrderTracking;
use AIArmada\Jnt\Features\Orders\Actions\WriteTrackingSnapshot;
use AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder;
use AIArmada\Jnt\Features\Orders\Api\DashboardStats;
use AIArmada\Jnt\Features\Orders\Api\ReadNotificationOrder;
use AIArmada\Jnt\Features\Orders\Api\ReadTrackingOrderOwner;
use AIArmada\Jnt\Features\Orders\Api\ReadWaybillOrder;
use AIArmada\Jnt\Features\Orders\Api\ReadWebhookOrder;
use AIArmada\Jnt\Features\Orders\Api\SyncStoredTracking;
use AIArmada\Jnt\Features\Orders\Api\UpdateTrackingSnapshot;
use AIArmada\Jnt\Features\Orders\Queries\FindNotificationOrder;
use AIArmada\Jnt\Features\Orders\Queries\FindTrackingOrderOwner;
use AIArmada\Jnt\Features\Orders\Queries\FindWaybillOrder;
use AIArmada\Jnt\Features\Orders\Queries\FindWebhookOrder;
use AIArmada\Jnt\Features\Orders\Queries\JntStatsAggregator;
use AIArmada\Jnt\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(SyncStoredTracking::class, SyncStoredOrderTracking::class);
        $app->bind(ReadWebhookOrder::class, FindWebhookOrder::class);
        $app->bind(ReadTrackingOrderOwner::class, FindTrackingOrderOwner::class);
        $app->bind(CancelStoredOrder::class, CancelStoredJntOrder::class);
        $app->bind(DashboardStats::class, JntStatsAggregator::class);
        $app->bind(ReadNotificationOrder::class, FindNotificationOrder::class);
        $app->bind(ReadWaybillOrder::class, FindWaybillOrder::class);
        $app->bind(UpdateTrackingSnapshot::class, WriteTrackingSnapshot::class);
    }

    public function boot(Application $app): void {}
}
