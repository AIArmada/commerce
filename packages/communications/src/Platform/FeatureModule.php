<?php

declare(strict_types=1);

namespace AIArmada\Communications\Platform;

use Illuminate\Contracts\Foundation\Application;

interface FeatureModule
{
    public function register(Application $app): void;

    public function boot(Application $app): void;
}
