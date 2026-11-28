<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Interactions;

use AIArmada\FilamentEngagement\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void {}
}
