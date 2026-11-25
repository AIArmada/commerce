<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(Api\UplineApi::class, Actions\UplineGateway::class);
        $app->bind(Api\RankQualificationApi::class, Actions\QualifyAffiliateRank::class);
        $app->bind(Api\RankAdministration::class, Actions\AdministerRank::class);
    }

    public function boot(Application $app): void {}
}
