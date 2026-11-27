<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity;

use AIArmada\Persons\Features\Identity\Actions\CleanupPersonDependents;
use AIArmada\Persons\Features\Identity\Api\PersonDependentsCleaner;
use AIArmada\Persons\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PersonDependentsCleaner::class, CleanupPersonDependents::class);
    }

    public function boot(Application $app): void {}
}
