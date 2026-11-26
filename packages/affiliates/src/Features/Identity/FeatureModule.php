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
        $app->bind(Api\CustomerDiscountReader::class, Queries\ReadCustomerDiscount::class);
        $app->bind(Api\AffiliateReferenceReader::class, Queries\AffiliateReferenceQuery::class);
        $app->bind(Api\AffiliateProfileReader::class, Queries\ReadAffiliateProfiles::class);
        $app->bind(Api\AffiliateAccessGuard::class, Queries\GuardAffiliateAccess::class);
        $app->bind(Api\AffiliateReportingReader::class, Queries\ReadAffiliateReporting::class);
        $app->bind(Api\AffiliateBonusReader::class, Queries\ReadAffiliateBonusCandidates::class);
        $app->bind(Api\AffiliateLifecycleReader::class, Queries\AffiliateLifecycleQuery::class);
        $app->bind(Api\AffiliateVoucherProvisioning::class, Actions\ProvisionAffiliateVoucher::class);
        $app->bind(Api\RegistrationApi::class, Actions\RegisterAffiliate::class);
        $app->bind(Api\IdentityProofApi::class, Actions\ManageIdentityProofs::class);
        $app->bind(Api\PortalProfileAdministration::class, Actions\UpdatePortalProfile::class);
        $app->bind(Api\AffiliateAdministration::class, Actions\AdministerAffiliate::class);
        $app->bind(Api\AffiliateRemoval::class, Actions\DeleteAffiliate::class);
        $app->bind(Api\AffiliateRankMaintenance::class, Actions\DetachAffiliateRank::class);
    }

    public function boot(Application $app): void {}
}
