<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Shipments;

use AIArmada\Shipping\Features\Shipments\Actions\IssuePrintableLabel;
use AIArmada\Shipping\Features\Shipments\Actions\WriteManualShipment;
use AIArmada\Shipping\Features\Shipments\Api\ManageManualShipment;
use AIArmada\Shipping\Features\Shipments\Actions\OperateStoredShipment;
use AIArmada\Shipping\Features\Shipments\Actions\MarkStoredShipmentPickedUp;
use AIArmada\Shipping\Features\Shipments\Api\MarkShipmentPickedUp;
use AIArmada\Shipping\Features\Shipments\Api\PrintableLabel;
use AIArmada\Shipping\Features\Shipments\Api\ShipmentOperations;
use AIArmada\Shipping\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ShipmentOperations::class, OperateStoredShipment::class);
        $app->bind(ManageManualShipment::class, WriteManualShipment::class);
        $app->bind(MarkShipmentPickedUp::class, MarkStoredShipmentPickedUp::class);
        $app->bind(PrintableLabel::class, IssuePrintableLabel::class);
    }

    public function boot(Application $app): void {}
}
