<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Lifecycle;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Contracts\DocumentLifecycle;
use AIArmada\Docs\Features\Lifecycle\Actions\DocumentOperations;
use AIArmada\Docs\Features\Lifecycle\Actions\ManageDocuments;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DocumentLifecycle::class, ManageDocuments::class);
        $app->bind(DocServiceInterface::class, DocumentOperations::class);
    }

    public function boot(Application $app): void {}
}
