<?php

declare(strict_types=1);

use AIArmada\Vouchers\AI\AbandonmentRisk;
use AIArmada\Vouchers\AI\ConversionPrediction;
use AIArmada\Vouchers\AI\DiscountRecommendation;
use AIArmada\Vouchers\AI\Enums\AbandonmentRiskLevel;
use AIArmada\Vouchers\AI\Enums\DiscountStrategy;
use AIArmada\Vouchers\AI\Enums\InterventionType;
use AIArmada\Vouchers\AI\Enums\PredictionConfidence;
use AIArmada\Vouchers\AI\VoucherMatch;
use AIArmada\Vouchers\Models\Voucher;

describe('ConversionPrediction', function (): void {
    it('creates with all properties', function (): void {
        $prediction = new ConversionPrediction(
            probability: 0.75,
            confidence: 0.8,
            factors: ['factor1' => 'value1'],
            withVoucher: 0.85,
            withoutVoucher: 0.65,
            incrementalLift: 0.2,
        );

        expect($prediction->probability)->toBe(0.75);
        expect($prediction->confidence)->toBe(0.8);
        expect($prediction->factors)->toBe(['factor1' => 'value1']);
        expect($prediction->withVoucher)->toBe(0.85);
        expect($prediction->withoutVoucher)->toBe(0.65);
        expect($prediction->incrementalLift)->toBe(0.2);
    });

    it('creates high probability prediction', function (): void {
        $prediction = ConversionPrediction::high();

        expect($prediction->probability)->toBe(0.8);
        expect($prediction->confidence)->toBe(0.7);
        expect($prediction->isHighProbability())->toBeTrue();
    });

    it('creates low probability prediction', function (): void {
        $prediction = ConversionPrediction::low();

        expect($prediction->probability)->toBe(0.2);
        expect($prediction->isLowProbability())->toBeTrue();
    });

    it('creates uncertain prediction', function (): void {
        $prediction = ConversionPrediction::uncertain();

        expect($prediction->probability)->toBe(0.5);
        expect($prediction->confidence)->toBe(0.3);
    });

    it('identifies high probability correctly', function (): void {
        expect((new ConversionPrediction(probability: 0.7, confidence: 0.5))->isHighProbability())->toBeTrue();
        expect((new ConversionPrediction(probability: 0.69, confidence: 0.5))->isHighProbability())->toBeFalse();
    });

    it('identifies low probability correctly', function (): void {
        expect((new ConversionPrediction(probability: 0.29, confidence: 0.5))->isLowProbability())->toBeTrue();
        expect((new ConversionPrediction(probability: 0.3, confidence: 0.5))->isLowProbability())->toBeFalse();
    });

    it('evaluates voucher worth correctly', function (): void {
        $worthIt = new ConversionPrediction(probability: 0.5, confidence: 0.5, incrementalLift: 0.2);
        $notWorthIt = new ConversionPrediction(probability: 0.5, confidence: 0.5, incrementalLift: 0.1);

        expect($worthIt->voucherWorthIt())->toBeTrue();
        expect($notWorthIt->voucherWorthIt())->toBeFalse();
    });

    it('detects potential cannibalization', function (): void {
        $cannibalizing = new ConversionPrediction(
            probability: 0.8,
            confidence: 0.7,
            withVoucher: 0.8,
            withoutVoucher: 0.75,
            incrementalLift: 0.05,
        );

        expect($cannibalizing->isPotentialCannibalization())->toBeTrue();
    });

    it('gets confidence level enum', function (): void {
        $prediction = new ConversionPrediction(probability: 0.5, confidence: 0.75);

        expect($prediction->getConfidenceLevel())->toBe(PredictionConfidence::High);
    });

    it('checks trustworthiness', function (): void {
        $trustworthy = new ConversionPrediction(probability: 0.5, confidence: 0.6);
        $notTrustworthy = new ConversionPrediction(probability: 0.5, confidence: 0.3);

        expect($trustworthy->isTrustworthy())->toBeTrue();
        expect($notTrustworthy->isTrustworthy())->toBeFalse();
    });

    it('generates summary', function (): void {
        $prediction = new ConversionPrediction(probability: 0.75, confidence: 0.8);

        expect($prediction->getSummary())->toContain('75%');
        expect($prediction->getSummary())->toContain('80%');
    });

    it('converts to array', function (): void {
        $prediction = new ConversionPrediction(
            probability: 0.75,
            confidence: 0.8,
            incrementalLift: 0.2,
        );

        $array = $prediction->toArray();

        expect($array)->toHaveKey('probability');
        expect($array)->toHaveKey('confidence');
        expect($array)->toHaveKey('is_high_probability');
        expect($array)->toHaveKey('voucher_worth_it');
        expect($array['probability'])->toBe(0.75);
    });
});

describe('AbandonmentRisk', function (): void {
    it('creates with all properties', function (): void {
        $time = now()->addMinutes(30);
        $risk = new AbandonmentRisk(
            riskScore: 0.7,
            riskLevel: AbandonmentRiskLevel::High,
            riskFactors: ['cart_age' => ['score' => 0.3]],
            predictedAbandonmentTime: $time,
            suggestedIntervention: InterventionType::DiscountOffer,
        );

        expect($risk->riskScore)->toBe(0.7);
        expect($risk->riskLevel)->toBe(AbandonmentRiskLevel::High);
        expect($risk->riskFactors)->toHaveKey('cart_age');
        expect($risk->predictedAbandonmentTime)->toBe($time);
        expect($risk->suggestedIntervention)->toBe(InterventionType::DiscountOffer);
    });

    it('creates low risk assessment', function (): void {
        $risk = AbandonmentRisk::low();

        expect($risk->riskScore)->toBe(0.1);
        expect($risk->riskLevel)->toBe(AbandonmentRiskLevel::Low);
        expect($risk->suggestedIntervention)->toBe(InterventionType::None);
    });

    it('creates high risk assessment', function (): void {
        $risk = AbandonmentRisk::high(0.75, ['reason' => 'old cart']);

        expect($risk->riskScore)->toBe(0.75);
        expect($risk->riskLevel)->toBe(AbandonmentRiskLevel::High);
        expect($risk->riskFactors)->toHaveKey('reason');
    });

    it('creates critical risk assessment', function (): void {
        $risk = AbandonmentRisk::critical();

        expect($risk->riskScore)->toBe(0.9);
        expect($risk->riskLevel)->toBe(AbandonmentRiskLevel::Critical);
        expect($risk->predictedAbandonmentTime)->not->toBeNull();
    });

    it('checks immediate action requirement', function (): void {
        expect(AbandonmentRisk::low()->requiresImmediateAction())->toBeFalse();
        expect(AbandonmentRisk::high()->requiresImmediateAction())->toBeTrue();
    });

    it('checks discount offer requirement', function (): void {
        $withDiscount = AbandonmentRisk::high();
        $withoutDiscount = AbandonmentRisk::low();

        expect($withDiscount->shouldOfferDiscount())->toBeTrue();
        expect($withoutDiscount->shouldOfferDiscount())->toBeFalse();
    });

    it('calculates minutes until abandonment', function (): void {
        $risk = new AbandonmentRisk(
            riskScore: 0.7,
            riskLevel: AbandonmentRiskLevel::High,
            predictedAbandonmentTime: now()->addMinutes(15),
        );

        $minutes = $risk->getMinutesUntilAbandonment();
        expect($minutes)->toBeGreaterThanOrEqual(14);
        expect($minutes)->toBeLessThanOrEqual(16);
    });

    it('returns null for minutes when no prediction time', function (): void {
        $risk = AbandonmentRisk::low();

        expect($risk->getMinutesUntilAbandonment())->toBeNull();
    });

    it('detects imminent abandonment', function (): void {
        $imminent = new AbandonmentRisk(
            riskScore: 0.9,
            riskLevel: AbandonmentRiskLevel::Critical,
            predictedAbandonmentTime: now()->addMinutes(5),
        );
        $notImminent = new AbandonmentRisk(
            riskScore: 0.9,
            riskLevel: AbandonmentRiskLevel::Critical,
            predictedAbandonmentTime: now()->addMinutes(30),
        );

        expect($imminent->isImminent())->toBeTrue();
        expect($notImminent->isImminent())->toBeFalse();
    });

    it('calculates priority score', function (): void {
        $highPriority = new AbandonmentRisk(
            riskScore: 0.9,
            riskLevel: AbandonmentRiskLevel::Critical,
            predictedAbandonmentTime: now()->addMinutes(5),
        );
        $lowPriority = AbandonmentRisk::low();

        expect($highPriority->getPriorityScore())->toBeGreaterThan($lowPriority->getPriorityScore());
    });

    it('gets top risk factors', function (): void {
        $risk = new AbandonmentRisk(
            riskScore: 0.7,
            riskLevel: AbandonmentRiskLevel::High,
            riskFactors: [
                'cart_age' => 0.3,
                'guest_user' => 0.2,
                'price' => 0.1,
                'device' => 0.1,
            ],
        );

        $top = $risk->getTopRiskFactors(2);
        expect($top)->toHaveCount(2);
        expect(array_keys($top))->toContain('cart_age');
    });

    it('generates summary', function (): void {
        $risk = AbandonmentRisk::high(0.75);

        expect($risk->getSummary())->toContain('High Risk');
        expect($risk->getSummary())->toContain('75%');
    });

    it('converts to array', function (): void {
        $risk = AbandonmentRisk::high();
        $array = $risk->toArray();

        expect($array)->toHaveKey('risk_score');
        expect($array)->toHaveKey('risk_level');
        expect($array)->toHaveKey('suggested_intervention');
        expect($array)->toHaveKey('requires_immediate_action');
    });
});

describe('DiscountRecommendation', function (): void {
    it('creates with all properties', function (): void {
        $recommendation = new DiscountRecommendation(
            recommendedDiscountCents: 1000,
            recommendedStrategy: DiscountStrategy::Percentage,
            expectedConversionLift: 0.15,
            expectedMarginImpact: -0.05,
            expectedROI: 2.5,
            alternatives: [['discount' => 500]],
        );

        expect($recommendation->recommendedDiscountCents)->toBe(1000);
        expect($recommendation->recommendedStrategy)->toBe(DiscountStrategy::Percentage);
        expect($recommendation->expectedConversionLift)->toBe(0.15);
        expect($recommendation->expectedMarginImpact)->toBe(-0.05);
        expect($recommendation->expectedROI)->toBe(2.5);
        expect($recommendation->alternatives)->toHaveCount(1);
    });

    it('creates no discount recommendation', function (): void {
        $recommendation = DiscountRecommendation::noDiscount();

        expect($recommendation->recommendedDiscountCents)->toBe(0);
        expect($recommendation->hasDiscount())->toBeFalse();
    });

    it('creates percentage discount recommendation', function (): void {
        $recommendation = DiscountRecommendation::percentage(1000);

        expect($recommendation->recommendedDiscountCents)->toBe(1000);
        expect($recommendation->recommendedStrategy)->toBe(DiscountStrategy::Percentage);
        expect($recommendation->hasDiscount())->toBeTrue();
    });

    it('creates fixed amount recommendation', function (): void {
        $recommendation = DiscountRecommendation::fixedAmount(500);

        expect($recommendation->recommendedDiscountCents)->toBe(500);
        expect($recommendation->recommendedStrategy)->toBe(DiscountStrategy::FixedAmount);
    });

    it('checks if discount exists', function (): void {
        expect(DiscountRecommendation::percentage(100)->hasDiscount())->toBeTrue();
        expect(DiscountRecommendation::noDiscount()->hasDiscount())->toBeFalse();
    });

    it('checks profitability', function (): void {
        $profitable = new DiscountRecommendation(
            recommendedDiscountCents: 1000,
            recommendedStrategy: DiscountStrategy::Percentage,
            expectedConversionLift: 0.2,
            expectedMarginImpact: -0.05,
            expectedROI: 1.5,
        );
        $notProfitable = new DiscountRecommendation(
            recommendedDiscountCents: 1000,
            recommendedStrategy: DiscountStrategy::Percentage,
            expectedConversionLift: 0.05,
            expectedMarginImpact: -0.15,
            expectedROI: 0.5,
        );

        expect($profitable->isProfitable())->toBeTrue();
        expect($notProfitable->isProfitable())->toBeFalse();
    });

    it('identifies high value recommendations', function (): void {
        $highValue = new DiscountRecommendation(
            recommendedDiscountCents: 1000,
            recommendedStrategy: DiscountStrategy::Percentage,
            expectedConversionLift: 0.2,
            expectedMarginImpact: -0.05,
            expectedROI: 2.5,
        );

        expect($highValue->isHighValue())->toBeTrue();
    });

    it('formats discount correctly', function (): void {
        $recommendation = DiscountRecommendation::percentage(1050);

        expect($recommendation->getFormattedDiscount())->toBe('$10.50');
        expect($recommendation->getFormattedDiscount('€'))->toBe('€10.50');
    });

    it('calculates expected net gain', function (): void {
        $recommendation = new DiscountRecommendation(
            recommendedDiscountCents: 1000,
            recommendedStrategy: DiscountStrategy::Percentage,
            expectedConversionLift: 0.2,
            expectedMarginImpact: -0.05,
            expectedROI: 2.0,
        );

        $gain = $recommendation->getExpectedNetGainCents(10000);
        expect($gain)->toBe(1000); // 10000 * 0.2 - 1000
    });

    it('checks for alternatives', function (): void {
        $withAlts = new DiscountRecommendation(
            recommendedDiscountCents: 1000,
            recommendedStrategy: DiscountStrategy::Percentage,
            expectedConversionLift: 0.2,
            expectedMarginImpact: -0.05,
            expectedROI: 2.0,
            alternatives: [['discount' => 500]],
        );
        $withoutAlts = DiscountRecommendation::noDiscount();

        expect($withAlts->hasAlternatives())->toBeTrue();
        expect($withoutAlts->hasAlternatives())->toBeFalse();
    });

    it('gets best alternative', function (): void {
        $recommendation = new DiscountRecommendation(
            recommendedDiscountCents: 1000,
            recommendedStrategy: DiscountStrategy::Percentage,
            expectedConversionLift: 0.2,
            expectedMarginImpact: -0.05,
            expectedROI: 2.0,
            alternatives: [
                ['discount' => 500, 'roi' => 1.8],
                ['discount' => 750, 'roi' => 1.5],
            ],
        );

        $best = $recommendation->getBestAlternative();
        expect($best)->toHaveKey('discount');
        expect($best['discount'])->toBe(500);
    });

    it('generates summary', function (): void {
        $recommendation = DiscountRecommendation::percentage(1000, 0.15, 2.5);

        expect($recommendation->getSummary())->toContain('$10.00');
        expect($recommendation->getSummary())->toContain('15%');
        expect($recommendation->getSummary())->toContain('2.5x');
    });

    it('converts to array', function (): void {
        $recommendation = DiscountRecommendation::percentage(1000);
        $array = $recommendation->toArray();

        expect($array)->toHaveKey('recommended_discount_cents');
        expect($array)->toHaveKey('recommended_strategy');
        expect($array)->toHaveKey('has_discount');
        expect($array)->toHaveKey('is_profitable');
    });
});

describe('VoucherMatch', function (): void {
    it('creates none match', function (): void {
        $match = VoucherMatch::none();

        expect($match->voucher)->toBeNull();
        expect($match->matchScore)->toBe(0.0);
        expect($match->hasMatch())->toBeFalse();
    });

    it('checks has match', function (): void {
        $none = VoucherMatch::none();

        expect($none->hasMatch())->toBeFalse();
    });

    it('identifies strong match', function (): void {
        $strong = new VoucherMatch(voucher: null, matchScore: 0.8);
        $weak = new VoucherMatch(voucher: null, matchScore: 0.5);

        expect($strong->isStrongMatch())->toBeTrue();
        expect($weak->isStrongMatch())->toBeFalse();
    });

    it('gets top reasons', function (): void {
        $match = new VoucherMatch(
            voucher: null,
            matchScore: 0.8,
            matchReasons: [
                'value' => 0.3,
                'timing' => 0.2,
                'segment' => 0.2,
                'extra' => 0.1,
            ],
        );

        $top = $match->getTopReasons(2);
        expect($top)->toHaveCount(2);
    });

    it('gets best alternative', function (): void {
        $match = new VoucherMatch(
            voucher: null,
            matchScore: 0.8,
            alternatives: [
                ['voucher_id' => 'alt1', 'score' => 0.7],
                ['voucher_id' => 'alt2', 'score' => 0.6],
            ],
        );

        $best = $match->getBestAlternative();
        expect($best)->toHaveKey('voucher_id');
        expect($best['voucher_id'])->toBe('alt1');
    });

    it('generates summary for no match', function (): void {
        $match = VoucherMatch::none();

        expect($match->getSummary())->toContain('No voucher match');
    });

    it('converts to array', function (): void {
        $match = VoucherMatch::none();
        $array = $match->toArray();

        expect($array)->toHaveKey('voucher_id');
        expect($array)->toHaveKey('match_score');
        expect($array)->toHaveKey('has_match');
        expect($array)->toHaveKey('is_strong_match');
    });
});
