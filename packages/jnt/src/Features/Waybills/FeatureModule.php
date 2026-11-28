<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Waybills;

use AIArmada\Jnt\Features\Waybills\Actions\IssuePrintableWaybill;
use AIArmada\Jnt\Features\Waybills\Api\PrintableWaybill;
use AIArmada\Jnt\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PrintableWaybill::class, IssuePrintableWaybill::class);
    }

    public function boot(Application $app): void {}
}
