<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(Api\AffiliateDirectory::class, Queries\AffiliateDirectoryQuery::class);
        $app->bind(Api\RegistrationApi::class, Actions\RegisterAffiliate::class);
        $app->bind(Api\IdentityProofApi::class, Actions\ManageIdentityProofs::class);
    }

    public function boot(Application $app): void {}
}
