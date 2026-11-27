<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Analytics;

use AIArmada\FilamentFeedback\Features\Analytics\Queries\FeedbackDashboardMemo;
use AIArmada\FilamentFeedback\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->scoped(FeedbackDashboardMemo::class);
    }

    public function boot(Application $app): void {}
}
