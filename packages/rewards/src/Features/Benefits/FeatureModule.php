<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits;

use AIArmada\Rewards\Contracts\RewardBenefitCatalog;
use AIArmada\Rewards\Features\Benefits\Actions\RewardBenefitCatalogService;
use AIArmada\Rewards\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(RewardBenefitCatalog::class, RewardBenefitCatalogService::class);
    }

    public function boot(Application $app): void {}
}
