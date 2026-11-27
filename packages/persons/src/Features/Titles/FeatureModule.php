<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles;

use AIArmada\Persons\Features\Titles\Actions\DeletePersonTitles;
use AIArmada\Persons\Features\Titles\Api\PersonTitlesCleaner;
use AIArmada\Persons\Features\Titles\Queries\FormatPersonTitleName;
use AIArmada\Persons\Features\Titles\Queries\TitleRelationModelResolver;
use AIArmada\Persons\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Persons\Runtime\PersonTitleFormatting;
use AIArmada\Persons\Runtime\TitleRelationModel;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(TitleRelationModel::class, TitleRelationModelResolver::class);
        $app->bind(PersonTitlesCleaner::class, DeletePersonTitles::class);
        $app->bind(PersonTitleFormatting::class, FormatPersonTitleName::class);
    }

    public function boot(Application $app): void {}
}
