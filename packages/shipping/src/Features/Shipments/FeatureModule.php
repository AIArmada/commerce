<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Shipments;

use AIArmada\Shipping\Features\Shipments\Actions\OperateStoredShipment;
use AIArmada\Shipping\Features\Shipments\Api\ShipmentOperations;
use AIArmada\Shipping\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ShipmentOperations::class, OperateStoredShipment::class);
    }

    public function boot(Application $app): void {}
}
