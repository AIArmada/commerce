<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders;

use AIArmada\Jnt\Features\Orders\Actions\CancelStoredJntOrder;
use AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder;
use AIArmada\Jnt\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(CancelStoredOrder::class, CancelStoredJntOrder::class);
    }

    public function boot(Application $app): void {}
}
