<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionTemplate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateVolumeTier;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use OwenIt\Auditing\Contracts\Auditable;

it('affiliate core and financial models are auditable and activity loggable', function (): void {
    $models = [
        Affiliate::class,
        AffiliateBalance::class,
        AffiliateCommissionPromotion::class,
        AffiliateCommissionRule::class,
        AffiliateCommissionTemplate::class,
        AffiliateFraudSignal::class,
        AffiliateLink::class,
        AffiliateUpline::class,
        AffiliatePayout::class,
        AffiliatePayoutEvent::class,
        AffiliatePayoutHold::class,
        AffiliatePayoutMethod::class,
        AffiliateProgram::class,
        AffiliateProgramCreative::class,
        AffiliateProgramTier::class,
        AffiliateRank::class,
        AffiliateRankHistory::class,
        AffiliateSupportTicket::class,
        AffiliateTaxDocument::class,
        AffiliateVolumeTier::class,
    ];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(HasCommerceAudit::class)
            ->and($traits)->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeTrue();
    }
});

it('affiliate analytics and free-text models intentionally remain without audit/activity traits', function (): void {
    foreach ([AffiliateDailyStat::class, AffiliateSupportMessage::class] as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->not->toContain(HasCommerceAudit::class)
            ->and($traits)->not->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeFalse();
    }
});
