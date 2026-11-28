<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits;

use AIArmada\Rewards\Contracts\RewardBenefitCatalog;
use AIArmada\Rewards\Features\Benefits\Actions\RewardBenefitCatalogService;
use AIArmada\Rewards\Features\Benefits\Api\RewardBenefitDirectoryApi;
use AIArmada\Rewards\Features\Benefits\Api\RewardBenefitRecoveryApi;
use AIArmada\Rewards\Features\Benefits\Api\RewardBenefitStockReadApi;
use AIArmada\Rewards\Features\Benefits\Queries\RewardBenefitDirectoryQuery;
use AIArmada\Rewards\Features\Benefits\Queries\RewardBenefitRecoveryQuery;
use AIArmada\Rewards\Features\Benefits\Queries\RewardBenefitStockReadQuery;
use AIArmada\Rewards\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(RewardBenefitCatalog::class, RewardBenefitCatalogService::class);
        $app->bind(RewardBenefitRecoveryApi::class, RewardBenefitRecoveryQuery::class);
        $app->bind(RewardBenefitDirectoryApi::class, RewardBenefitDirectoryQuery::class);
        $app->bind(RewardBenefitStockReadApi::class, RewardBenefitStockReadQuery::class);
    }

    public function boot(Application $app): void {}
}
