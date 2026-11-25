<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->scoped(Api\MerchantCatalog::class, Queries\ProgramCatalogQuery::class);
        $app->bind(Api\MembershipAdministration::class, Actions\AdministerProgramMembership::class);
        $app->bind(Api\MembershipReviewApi::class, Actions\ReviewProgramMembership::class);
        $app->bind(Api\ProgramEnrollment::class, Actions\ManageProgramEnrollment::class);
        $app->bind(Api\ProgramMembershipReader::class, Queries\ProgramMembershipQuery::class);
        $app->bind(Api\ProgramTierManagement::class, Actions\UpgradeProgramTier::class);
        $app->bind(Api\TierQualificationReader::class, Queries\ProgramTierQualificationQuery::class);
        $app->bind(Api\ProgramPortalReader::class, Queries\ProgramPortalQuery::class);
    }

    public function boot(Application $app): void {}
}
