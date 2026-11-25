<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions;

use AIArmada\Affiliates\Contracts\MerchantLedger;
use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(Api\ConversionAdministration::class, Actions\AdministerConversion::class);
        $app->bind(Api\CartConversionRecording::class, Actions\RecordAffiliateConversion::class);
        $app->bind(Api\AwardPosting::class, Actions\RecordAffiliateAward::class);
        $app->bind(Api\OutcomePosting::class, Actions\RecordAffiliateOutcome::class);
        $app->bind(Api\PerformanceBonusPosting::class, Actions\AwardPerformanceBonuses::class);
        $app->bind(Api\ConversionMaturity::class, Actions\ProcessConversionMaturity::class);
        $app->bind(Api\PayoutReservation::class, Actions\ReservePayoutFunds::class);
        $app->bind(Api\PayoutConversionSettlement::class, Actions\SettlePayoutConversions::class);
        $app->scoped(MerchantLedger::class, Actions\ExternalConversionLedger::class);
    }

    public function boot(Application $app): void {}
}
