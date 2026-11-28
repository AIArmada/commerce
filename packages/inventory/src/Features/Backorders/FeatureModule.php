<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Backorders;

use AIArmada\Inventory\Features\Backorders\Persistence\BackorderService;
use AIArmada\Inventory\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(BackorderService::class);
    }

    public function boot(Application $app): void {}
}
