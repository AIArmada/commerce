<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Credentials;

use AIArmada\Persons\Features\Credentials\Actions\AssignCredentialAction;
use AIArmada\Persons\Features\Credentials\Actions\DeletePersonCredentials;
use AIArmada\Persons\Features\Credentials\Api\PersonCredentialsCleaner;
use AIArmada\Persons\Features\Credentials\Queries\CredentialRelationModelResolver;
use AIArmada\Persons\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Persons\Runtime\CredentialRelationModel;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(CredentialRelationModel::class, CredentialRelationModelResolver::class);
        $app->bind(PersonCredentialsCleaner::class, DeletePersonCredentials::class);
        $app->bind(AssignCredentialAction::class);
    }

    public function boot(Application $app): void {}
}
