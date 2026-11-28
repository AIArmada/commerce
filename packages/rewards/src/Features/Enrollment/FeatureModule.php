<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Enrollment;

use AIArmada\Rewards\Features\Enrollment\Api\RewardEnrollmentConsentApi;
use AIArmada\Rewards\Features\Enrollment\Queries\RewardEnrollmentConsentQuery;
use AIArmada\Rewards\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(RewardEnrollmentConsentApi::class, RewardEnrollmentConsentQuery::class);
    }

    public function boot(Application $app): void {}
}
