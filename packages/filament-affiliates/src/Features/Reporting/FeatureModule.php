<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Reporting;

use AIArmada\FilamentAffiliates\Features\Reporting\Queries\AffiliateStatsAggregator;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(AffiliateStatsAggregator::class);
    }

    public function boot(Application $app): void
    {}
}
