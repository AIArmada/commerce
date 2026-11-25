<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(Api\CommissionTemplateAdministration::class, Actions\AdministerCommissionTemplates::class);
        $app->bind(Api\CommissionTemplateReader::class, Queries\CommissionTemplateQuery::class);
        $app->bind(Api\VolumeTierAdministration::class, Actions\AdministerVolumeTier::class);
        $app->bind(Api\VolumeTierReader::class, Queries\VolumeTierQuery::class);
        $app->bind(Api\ProgramCommissionCleanup::class, Actions\DeleteProgramCommissionConfiguration::class);
        $app->bind(Api\CommissionPricing::class, Queries\CalculateCommissionPricing::class);
        $app->bind(Api\CommissionRuleCalculation::class, Actions\CalculateRuleCommission::class);
        $app->bind(Api\CommissionRulePreview::class, Queries\FindCommissionRules::class);
        $app->bind(Api\PerformanceBonusCalculation::class, Queries\CalculatePerformanceBonuses::class);
        $app->bind(Api\PromotionUsage::class, Actions\ConsumePromotionUsage::class);
        $app->bind(Api\ProgramCommissionAdministration::class, Actions\AdministerProgramCommissions::class);
    }

    public function boot(Application $app): void {}
}
