<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Suppressions;

use AIArmada\Communications\Features\Suppressions\Actions\CreateSuppressionAction;
use AIArmada\Communications\Features\Suppressions\Actions\LiftSuppressionAction;
use AIArmada\Communications\Features\Suppressions\Queries\SuppressionAuditModelResolver;
use AIArmada\Communications\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Communications\Runtime\SuppressionAuditModel;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(SuppressionAuditModel::class, SuppressionAuditModelResolver::class);
        $app->bind(CreateSuppressionAction::class);
        $app->bind(LiftSuppressionAction::class);
    }

    public function boot(Application $app): void {}
}
