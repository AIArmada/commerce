<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements;

use AIArmada\Rewards\Features\Entitlements\Actions\ManagePointsEntitlements;
use AIArmada\Rewards\Features\Entitlements\Actions\RecordConsumedPointsDispute;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardLockApi;
use AIArmada\Rewards\Features\Entitlements\Api\RewardDecisionHistoryApi;
use AIArmada\Rewards\Features\Entitlements\Api\RewardDisputeApi;
use AIArmada\Rewards\Features\Entitlements\Api\RewardPointsEntitlementApi;
use AIArmada\Rewards\Features\Entitlements\Queries\RewardAwardLockQuery;
use AIArmada\Rewards\Features\Entitlements\Queries\RewardDecisionHistoryQuery;
use AIArmada\Rewards\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(RewardAwardLockApi::class, RewardAwardLockQuery::class);
        $app->bind(RewardDecisionHistoryApi::class, RewardDecisionHistoryQuery::class);
        $app->bind(RewardDisputeApi::class, RecordConsumedPointsDispute::class);
        $app->bind(RewardPointsEntitlementApi::class, ManagePointsEntitlements::class);
    }

    public function boot(Application $app): void {}
}
