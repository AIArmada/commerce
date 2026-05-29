<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateNetwork;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Models\AffiliateTrainingModule;
use AIArmada\Affiliates\Models\AffiliateTrainingProgress;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
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
        AffiliateNetwork::class,
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
        AffiliateTrainingModule::class,
        AffiliateTrainingProgress::class,
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
