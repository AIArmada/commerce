<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Versioning;

use AIArmada\Docs\Contracts\DocumentVersions;
use AIArmada\Docs\Features\Versioning\Actions\DocumentVersionOperations;
use AIArmada\Docs\Features\Versioning\Queries\VersionRelationModelResolver;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Docs\Runtime\VersionRelationModel;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DocumentVersions::class, DocumentVersionOperations::class);
        $app->bind(VersionRelationModel::class, VersionRelationModelResolver::class);
    }

    public function boot(Application $app): void {}
}
