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
        $app->bind(Api\CommissionPricing::class, Queries\CalculateCommissionPricing::class);
        $app->bind(Api\CommissionRuleCalculation::class, Actions\CalculateRuleCommission::class);
        $app->bind(Api\CommissionRulePreview::class, Queries\FindCommissionRules::class);
        $app->bind(Api\PerformanceBonusCalculation::class, Queries\CalculatePerformanceBonuses::class);
        $app->bind(Api\PromotionUsage::class, Actions\ConsumePromotionUsage::class);
    }

    public function boot(Application $app): void {}
}
