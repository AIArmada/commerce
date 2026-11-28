<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Templates;

use AIArmada\Communications\Features\Templates\Queries\TemplateRelationModelsResolver;
use AIArmada\Communications\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Communications\Runtime\TemplateRelationModels;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(TemplateRelationModels::class, TemplateRelationModelsResolver::class);
    }

    public function boot(Application $app): void {}
}
