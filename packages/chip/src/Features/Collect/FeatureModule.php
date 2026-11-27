<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect;

use AIArmada\Chip\Features\Collect\Actions\PurchasesApi;
use AIArmada\Chip\Features\Collect\Api\PurchaseOperations;
use AIArmada\Chip\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PurchaseOperations::class, PurchasesApi::class);
    }

    public function boot(Application $app): void {}
}
