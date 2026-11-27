<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles;

use AIArmada\Contacting\Contracts\SocialProfileNormalizer;
use AIArmada\Contacting\Features\SocialProfiles\Actions\NormalizeSocialProfileAction;
use AIArmada\Contacting\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(SocialProfileNormalizer::class, NormalizeSocialProfileAction::class);
    }

    public function boot(Application $app): void {}
}
