<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders;

use AIArmada\Jnt\Features\Orders\Actions\CancelStoredJntOrder;
use AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder;
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
        $app->bind(CancelStoredOrder::class, CancelStoredJntOrder::class);
        $app->bind(ReadWaybillOrder::class, FindWaybillOrder::class);
        $app->bind(UpdateTrackingSnapshot::class, WriteTrackingSnapshot::class);
    }

    public function boot(Application $app): void {}
}
