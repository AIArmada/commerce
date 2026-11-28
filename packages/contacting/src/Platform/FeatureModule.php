<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Platform;

use Illuminate\Contracts\Foundation\Application;

interface FeatureModule
{
    public function register(Application $app): void;

    public function boot(Application $app): void;
}
