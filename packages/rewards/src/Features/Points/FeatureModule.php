<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Points;

use AIArmada\Rewards\Features\Points\Actions\ManageRewardPoints;
use AIArmada\Rewards\Features\Points\Api\RewardPointsApi;
use AIArmada\Rewards\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(RewardPointsApi::class, ManageRewardPoints::class);
    }

    public function boot(Application $app): void {}
}
