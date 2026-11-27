<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Affiliations;

use AIArmada\Persons\Features\Affiliations\Actions\DeletePersonAffiliations;
use AIArmada\Persons\Features\Affiliations\Api\PersonAffiliationsCleaner;
use AIArmada\Persons\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PersonAffiliationsCleaner::class, DeletePersonAffiliations::class);
    }

    public function boot(Application $app): void {}
}
