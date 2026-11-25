<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Contracts\QualifiesForOpenApproval;
use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(MerchantIdentity::class, Queries\MerchantAffiliateQuery::class);
        $app->bind(QualifiesForOpenApproval::class, Queries\OpenApprovalPolicy::class);
        $app->bind(Api\AffiliateLifecycle::class, Actions\TransitionAffiliateStatus::class);
        $app->bind(Api\AffiliateDirectory::class, Queries\AffiliateDirectoryQuery::class);
        $app->bind(Api\RegistrationApi::class, Actions\RegisterAffiliate::class);
        $app->bind(Api\IdentityProofApi::class, Actions\ManageIdentityProofs::class);
        $app->bind(Api\PortalProfileAdministration::class, Actions\UpdatePortalProfile::class);
    }

    public function boot(Application $app): void {}
}
