<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(Api\ReportingApi::class, Queries\AffiliateReportQuery::class);
        $app->bind(Api\CohortApi::class, Queries\CohortQuery::class);
        $app->bind(Api\DailyStatsReader::class, Queries\DailyStatsQuery::class);
        $app->bind(Api\DailyStatsWriter::class, Actions\AggregateDailyStats::class);
    }

    public function boot(Application $app): void {}
}
