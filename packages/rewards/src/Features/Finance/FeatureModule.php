<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance;

use AIArmada\Rewards\Features\Finance\Actions\ManageRewardBudget;
use AIArmada\Rewards\Features\Finance\Api\RewardBudgetApi;
use AIArmada\Rewards\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(RewardBudgetApi::class, ManageRewardBudget::class);
    }

    public function boot(Application $app): void {}
}
