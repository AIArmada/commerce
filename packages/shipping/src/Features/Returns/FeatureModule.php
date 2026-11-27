<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Returns;

use AIArmada\Shipping\Features\Returns\Actions\ApproveReturnAuthorization;
use AIArmada\Shipping\Features\Returns\Actions\ManagePackageReturn;
use AIArmada\Shipping\Features\Returns\Actions\RejectReturnAuthorization;
use AIArmada\Shipping\Features\Returns\Api\ApproveReturn;
use AIArmada\Shipping\Features\Returns\Api\PackageReturnWorkflow;
use AIArmada\Shipping\Features\Returns\Api\RejectReturn;
use AIArmada\Shipping\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PackageReturnWorkflow::class, ManagePackageReturn::class);
        $app->bind(ApproveReturn::class, ApproveReturnAuthorization::class);
        $app->bind(RejectReturn::class, RejectReturnAuthorization::class);
    }

    public function boot(Application $app): void {}
}
