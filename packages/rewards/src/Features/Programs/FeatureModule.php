<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs;

use AIArmada\Rewards\Features\Programs\Api\RewardProgramDirectoryApi;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use AIArmada\Rewards\Features\Programs\Queries\RewardProgramDirectoryQuery;
use AIArmada\Rewards\Features\Programs\Queries\RewardProgramLockQuery;
use AIArmada\Rewards\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(RewardProgramLockApi::class, RewardProgramLockQuery::class);
        $app->bind(RewardProgramDirectoryApi::class, RewardProgramDirectoryQuery::class);
    }

    public function boot(Application $app): void {}
}
