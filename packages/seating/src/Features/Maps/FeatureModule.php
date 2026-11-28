<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps;

use AIArmada\Seating\Contracts\SeatLayoutInterface;
use AIArmada\Seating\Features\Maps\Api\MapsApi;
use AIArmada\Seating\Features\Maps\Queries\EloquentMapsApi;
use AIArmada\Seating\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Seating\Services\SeatLayoutRenderer;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(SeatLayoutInterface::class, SeatLayoutRenderer::class);
        $app->bind(MapsApi::class, EloquentMapsApi::class);
    }

    public function boot(Application $app): void {}
}
