<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Qualification;

use AIArmada\Rewards\Contracts\RewardPreviewEvaluator;
use AIArmada\Rewards\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(RewardPreviewEvaluator::class, RewardPreviewEngine::class);
    }

    public function boot(Application $app): void {}
}
