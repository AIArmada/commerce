<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms;

use AIArmada\Feedback\Features\Forms\Api\ReadsFormMetrics;
use AIArmada\Feedback\Features\Forms\Queries\ReadFormMetricsQuery;
use AIArmada\Feedback\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ReadsFormMetrics::class, ReadFormMetricsQuery::class);
    }

    public function boot(Application $app): void {}
}
