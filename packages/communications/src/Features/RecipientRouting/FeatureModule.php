<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\RecipientRouting;

use AIArmada\Communications\Features\RecipientRouting\Queries\PreferenceAuditModelResolver;
use AIArmada\Communications\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Communications\Runtime\PreferenceAuditModel;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PreferenceAuditModel::class, PreferenceAuditModelResolver::class);
    }

    public function boot(Application $app): void {}
}
