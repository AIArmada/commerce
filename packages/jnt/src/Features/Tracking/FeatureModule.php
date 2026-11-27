<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking;

use AIArmada\Jnt\Features\Tracking\Api\DeleteOrderTracking;
use AIArmada\Jnt\Features\Tracking\Persistence\OrderTrackingCleanup;
use AIArmada\Jnt\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(\AIArmada\Jnt\Features\Tracking\Api\StoreTrackingEvents::class, \AIArmada\Jnt\Features\Tracking\Persistence\TrackingEventWriter::class);
        $app->bind(\AIArmada\Jnt\Features\Tracking\Api\ReadLatestTrackingScan::class, \AIArmada\Jnt\Features\Tracking\Queries\LatestTrackingScan::class);
        $app->bind(DeleteOrderTracking::class, OrderTrackingCleanup::class);
    }

    public function boot(Application $app): void {}
}
