<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Templates;

use AIArmada\Docs\Contracts\DocumentTemplates;
use AIArmada\Docs\Features\Templates\Actions\ManageDocTemplates;
use AIArmada\Docs\Features\Templates\Queries\TemplateRelationModelResolver;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Docs\Runtime\TemplateRelationModel;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DocumentTemplates::class, ManageDocTemplates::class);
        $app->bind(TemplateRelationModel::class, TemplateRelationModelResolver::class);
    }

    public function boot(Application $app): void {}
}
