<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles;

use AIArmada\Persons\Features\Titles\Actions\DeletePersonTitles;
use AIArmada\Persons\Features\Titles\Api\PersonTitlesCleaner;
use AIArmada\Persons\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PersonTitlesCleaner::class, DeletePersonTitles::class);
    }

    public function boot(Application $app): void {}
}
